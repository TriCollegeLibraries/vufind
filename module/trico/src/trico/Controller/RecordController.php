<?php
namespace trico\Controller;

class RecordController extends \VuFind\Controller\RecordController
{
    /**
     * Action for dealing with holds.
     *
     * @return mixed
     */
    public function holdAction()
    {
        $driver = $this->loadRecord();
        
        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkHolds = $catalog->checkFunction("Holds", $driver->getUniqueID());
        if (!$checkHolds) {
            return $this->forwardTo('Record', 'Home');
        }

        // trico edit 2014.07.08 ah - next 3 code blocks add variables to 
        // enable context-sensitive links to tripodclassic
        $driver = $this->loadRecord();
        $ilsdriver = $catalog->getDriver();

        // make sure user is logged in
        $account = $this->getAuthManager();
        if ($account->isLoggedIn() == false) {
            $holdLink = $ilsdriver->getHoldLink($driver->getUniqueID());
            return $this->forceLogin(null, array('holdLink'=>$holdLink));
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLoginOnly($account))) {
            return $patron;
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->holds()->validateRequest($checkHolds['HMACKeys']);
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        if (!$catalog->checkRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        )) {
            return $this->blockedholdAction();
        }

        // Send various values to the view so we can build the form:
        $pickup = $catalog->getPickUpLocations($patron, $gatheredDetails);
        // TRICO edit 2012-10-02 ah - need this step b/c we can't get 
        // item ids from the holdings routine.
        $items = $catalog->getRequestableItems($patron, $gatheredDetails, $pickup);
        // TRICO edit 2014-08-22 ah: hacky; need to allow an error message through
        // occasionally you only know there's nothing requestable once you've tried
        $skipForm = false;
        if (is_string($items)) {
            // it's an error message; nothing was holdable
            $this->flashMessenger()->setNamespace('error')
                ->addMessage($items);
            $skipForm = true;
        }

        $extraHoldFields = isset($checkHolds['extraHoldFields'])
            ? explode(":", $checkHolds['extraHoldFields']) : array();

        // Process form submissions if necessary:
        if (!is_null($this->params()->fromPost('placeHold'))) {
            // If the form contained a pickup location or request group, make sure
            // they are valid:
            $valid = $this->holds()->validateRequestGroupInput(
                $gatheredDetails, $extraHoldFields, $requestGroups
            );
            if (!$valid) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('hold_invalid_request_group');
            } elseif (!$this->holds()->validatePickUpInput(
                $gatheredDetails['pickUpLocation'], $extraHoldFields, $pickup
            )) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('hold_invalid_pickup');
            } else {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.

                // Add Patron Data to Submitted Data
                $holdDetails = $gatheredDetails + array('patron' => $patron);

                // Attempt to place the hold:
                $function = (string)$checkHolds['function'];
                $results = $catalog->$function($holdDetails);

                // Success: Go to Display Holds
                if (isset($results['success']) && $results['success'] == true) {
                    $this->flashMessenger()->setNamespace('info')
                        ->addMessage('hold_place_success');
                    if ($this->inLightbox()) {
                        return false;
                    }
                    return $this->redirect()->toRoute('myresearch-holds');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()->setNamespace('error')
                            ->addMessage($results['status']);
                    }
                    if (isset($results['sysMessage'])) {
                        $this->flashMessenger()->setNamespace('error')
                            ->addMessage($results['sysMessage']);
                    }
                }
            }
        }

        // Find and format the default required date:
        $defaultRequired = $this->holds()->getDefaultRequiredDate(
            $checkHolds, $catalog, $patron, $gatheredDetails
        );
        $defaultRequired = $this->getServiceLocator()->get('VuFind\DateConverter')
            ->convertToDisplayDate("U", $defaultRequired);
        try {
            $defaultPickup
                = $catalog->getDefaultPickUpLocation($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultPickup = false;
        }
        try {
            $defaultRequestGroup = empty($requestGroups)
                ? false
                : $catalog->getDefaultRequestGroup($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultRequestGroup = false;
        }

        $requestGroupNeeded = in_array('requestGroup', $extraHoldFields)
            && !empty($requestGroups)
            && (empty($gatheredDetails['level'])
                || $gatheredDetails['level'] != 'copy');

        return $this->createViewModel(
            array(
                'gatheredDetails' => $gatheredDetails,
                'pickup' => $pickup,
                'items' => $items,
                'defaultPickup' => $defaultPickup,
                'homeLibrary' => $this->getUser()->home_library,
                'extraHoldFields' => $extraHoldFields,
                'defaultRequiredDate' => $defaultRequired,
                'requestGroups' => isset($requestGroups) ? $requestGroups : false,
                'defaultRequestGroup' => $defaultRequestGroup,
                'requestGroupNeeded' => $requestGroupNeeded,
                'helpText' => isset($checkHolds['helpText'])
                    ? $checkHolds['helpText'] : null,
                'skipForm' => $skipForm
            )
        );
    }

    /**
     * Action for dealing with bookings.
     *
     * @return mixed
     */
    public function bookingAction()
    {
        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkBookings = $catalog->checkFunction("Bookings");
        if (!$checkBookings) {
            return $this->forwardTo('Record', 'Home');
        }

        // trico edit 2014.07.08 ah - next 3 code blocks add variables to 
        // enable context-sensitive links to tripodclassic
        $driver = $this->loadRecord();
        $ilsdriver = $catalog->getDriver();

        // make sure user is logged in
        $account = $this->getAuthManager();
        if ($account->isLoggedIn() == false) {
            $bookingLink = $ilsdriver->getBookingLink($driver->getUniqueID());
            return $this->forceLogin(null, array('bookingLink'=>$bookingLink));
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLoginOnly($account))) {
            return $patron;
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->holds()->validateRequest($checkBookings['HMACKeys']);
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Send various values to the view so we can build the form
        $extraBookingFields = isset($checkBookings['extraBookingFields'])
            ? explode(":", $checkBookings['extraBookingFields']) : array();

        // view params
        $viewparams = array(
            'gatheredDetails' => $gatheredDetails,
            'extraBookingFields' => $extraBookingFields,
        );

        // Block invalid requests:
        if (!$catalog->checkRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        )) {
            return $this->blockedbookingAction();
        }

        // Process submitted form
        if (!is_null($this->params()->fromPost('placeBooking'))) {
            // TRICO edit 2012-11-19 ah - need a two-phase form.
            // this is coded to just re-use the same form both times, 
            // with different fields.  work done in driver.

            // Add Patron Data to Submitted Data
            $bookingDetails = $gatheredDetails + array('patron' => $patron);

            // Attempt to place the booking:
            $function = (string)$checkBookings['function'];
            $results = $catalog->$function($bookingDetails);

            // Success: Go to Display Bookings
            if (isset($results['success']) && $results['success'] == true) {
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('booking_place_success');
                if ($this->inLightbox()) {
                    return false;
                }
                return $this->redirect()->toRoute('myresearch-bookings');

            } else {
                // the form has two pages, set up through the same view.
                // we could be ready to process the 2nd page. 
                // or there could be errors.
                // either way stay on the current form.
                if (isset($results['status'])) {
                    $this->flashMessenger()->setNamespace('error')
                        ->addMessage($results['status']);
                }
                if (isset($results['sysMessage'])) {
                    $this->flashMessenger()->setNamespace('error')
                        ->addMessage($results['sysMessage']);
                }
                if (isset($results['items'])) {
                    // we're doing page 2
                    $viewparams['items'] = $results['items'];
                }

                // populate values already submitted
                if (isset($results['startdate'])) {
                    $viewparams['startdate'] = $results['startdate'];
                }
                if (isset($results['starttime'])) {
                    $viewparams['starttime'] = $results['starttime'];
                }

                if (isset($results['availability'])) {
                    // we're doing page 1 again (e.g. time submitted was in the past)
                    $availability = $results['availability'];
                    if (isset($availability['error'])) {
                        $this->flashMessenger()->setNamespace('error')
                            ->addMessage($availability['error']);
                    } else {
                        $viewparams['availability'] = json_encode($availability);
                        $viewparams['firstavailable'] = $availability['whitelist'][0];
                    }
                }
            }

        } else {
            // first time hitting the form; get booking date time data
            $availability = $catalog->getBookableDateTimes(
                $patron, $gatheredDetails
            );
            if (isset($availability['error'])) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage($availability['error']);
            } else {
                $viewparams['availability'] = json_encode($availability);
                $viewparams['firstavailable'] = $availability['whitelist'][0];
            }
        }

        return $this->createViewModel($viewparams);
    }

    /**
     * SMS action - Allows the SMS form to appear.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function smsAction()
    {
        // Retrieve the record driver:
        $driver = $this->loadRecord();

        // Load the SMS carrier list:
        $sms = $this->getServiceLocator()->get('VuFind\SMS');
        $view = $this->createViewModel();
        $view->carriers = $sms->getCarriers();
        $view->validation = $sms->getValidationType();

        // Process form submission:
        if ($this->params()->fromPost('submit')) {
            // Send parameters back to view so form can be re-populated:
            $view->to = $this->params()->fromPost('to');
            $view->provider = $this->params()->fromPost('provider');
            // trico edit 2014.04.22 ah - adding user-selected location
            $view->location = $this->params()->fromPost('location');

            // Attempt to send the email and show an appropriate flash message:
            try {
                $body = $this->getViewRenderer()->partial(
                    'Email/record-sms.phtml',
                    // trico edit 2014.04.22 ah
                    // adding user-selected location
                    array(
                      'driver' => $driver, 
                      'to' => $view->to, 
                      'location' => $view->location
                    )
                );
                $sms->text($view->provider, $view->to, null, $body);
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('sms_success');
                return $this->redirectToRecord();
            } catch (MailException $e) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage($e->getMessage());
            }
        }

        // Display the template:
        $view->setTemplate('record/sms');
        return $view;
    }

    // trico edit 2014.07.08 ah - add variables to enable context-sensitive
    // links to tripodclassic
    /**
     * Save action - Allows the save template to appear,
     *   passes containingLists & nonContainingLists
     *
     * @return mixed
     */
    public function saveAction()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            throw new \Exception('Lists disabled');
        }

        // Process form submission:
        if ($this->formWasSubmitted('submit')) {
            return $this->processSave();
        }

        // Retrieve user object and force login if necessary:
        if (!($user = $this->getUser())) {
            return $this->forceLogin(null, array('favoritesLink'=>true));
        }

        // If the user is logged in we can use parent method; it's a slight
        // inefficiency but keeps some code out of our local module.
        return parent::saveAction();
    }


    // trico edit 2014.07.08 ah - add variables to enable context-sensitive
    // links to tripodclassic
    // note: also overridden in MyResearchController
    /**
     * Redirect the user to the login screen.
     *
     * @param string $msg     Flash message to display on login screen
     * @param array  $extras  Associative array of extra fields to store
     * @param bool   $forward True to forward, false to redirect
     *
     * @return mixed
     */
    protected function forceLogin($msg = null, $extras = array(), $forward = true)
    {
        // Set default message if necessary.
        if (is_null($msg)) {
            $msg = 'You must be logged in first';
        }

        // Store the current URL as a login followup action unless we are in a
        // lightbox (since lightboxes use a different followup mechanism).
        if (!$this->inLightbox()) {
            $this->followup()->store($extras);
        } else {
            // If we're in a lightbox and using an authentication method
            // with a session initiator, the user will be redirected outside
            // of VuFind and then redirected back. Thus, we need to store a
            // followup URL to avoid losing context, but we don't want to
            // store the AJAX request URL that populated the lightbox. The
            // delightboxURL() routine will remap the URL appropriately.
            // We can set this whether or not there's a session initiator
            // because it will be cleared when needed.
            $url = $this->delightboxURL($this->getServerUrl());
            $this->followup()->store($extras, $url);
        }
        if (!empty($msg)) {
            $this->flashMessenger()->setNamespace('error')->addMessage($msg);
        }

        // Set a flag indicating that we are forcing login:
        $this->getRequest()->getPost()->set('forcingLogin', true);

        foreach ($extras as $key=>$val) {
            $this->getRequest()->getPost()->set($key, $val);
        }

        if ($forward) {
            return $this->forwardTo('MyResearch', 'Login');
        }
        return $this->redirect()->toRoute('myresearch-home');
    }

    /**
     * trico edit 2014.07.08 ah - tracks function catalogLogin!!!
     * split out due to local need to separate the forceLogin step
     *
     * @return bool|array
     */
    protected function catalogLoginOnly($account)
    {
        // Now check if the user has provided credentials with which to log in:
        if (($username = $this->params()->fromPost('cat_username', false))
            && ($password = $this->params()->fromPost('cat_password', false))
        ) {
            $patron = $account->newCatalogLogin($username, $password);

            // If login failed, store a warning message:
            if (!$patron) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('Invalid Patron Login');
            }
        } else {
            // If no credentials were provided, try the stored values:
            $patron = $account->storedCatalogLogin();
        }

        // If catalog login failed, send the user to the right page:
        if (!$patron) {
            return $this->forwardTo('MyResearch', 'CatalogLogin');
        }

        // Send value (either false or patron array) back to caller:
        return $patron;
    }

}
