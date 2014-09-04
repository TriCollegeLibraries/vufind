<?php
namespace trico\Controller;

class MyResearchController extends \VuFind\Controller\MyResearchController
{

    /**
     * Send list of holds to view
     *
     * @return mixed
     */
    public function holdsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Process cancel requests if necessary:
        $cancelStatus = $catalog->checkFunction('cancelHolds');
        $view = $this->createViewModel();
        $view->cancelResults = $cancelStatus
            ? $this->holds()->cancelHolds($catalog, $patron) : array();
        // If we need to confirm
        if (!is_array($view->cancelResults)) {
            return $view->cancelResults;
        }

        // By default, assume we will not need to display a cancel form:
        $view->cancelForm = false;

        // Get held item details:
        $result = $catalog->getMyHolds($patron);
        $recordList = array();
        $this->holds()->resetValidation();
        foreach ($result as $current) {
            // Add cancel details if appropriate:
            $current = $this->holds()->addCancelDetails(
                $catalog, $current, $cancelStatus
            );
            if ($cancelStatus && $cancelStatus['function'] != "getCancelHoldLink"
                && isset($current['cancel_details'])
            ) {
                // Enable cancel form if necessary:
                $view->cancelForm = true;
            }

            // trico edit 2013.12.03 ah - we added ill records in here, too
            // Build record driver:
            if ($current['ils_hold']) {
                $recordList['ils_records'][] = $this->getDriverForILSRecord($current);
            } else {
                $recordList['ill_records'][] = $current;
            }
              
        }

        // Get List of PickUp Libraries based on patron's home library
        try {
            $view->pickup = $catalog->getPickUpLocations($patron);
        } catch (\Exception $e) {
            // Do nothing; if we're unable to load information about pickup
            // locations, they are not supported and we should ignore them.
        }
        $view->recordList = $recordList;
        return $view;
    }

    /**
     * Send list of Bookings to view
     *
     * @return mixed
     */
    public function bookingsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Process cancel requests if necessary:
        $cancelStatus = $catalog->checkFunction('cancelBookings');
        $view = $this->createViewModel();
        $view->cancelResults = $cancelStatus
            ? $this->holds()->cancelBookings($catalog, $patron) : array();
        // If we need to confirm
        if (!is_array($view->cancelResults)) {
            return $view->cancelResults;
        }

        // sometimes getMyBookings comes back with one of the 
        //   canceled items; i guess because it queries faster
        //   than the system can delete.  (hack alert)
        sleep(1);

        // By default, assume we will not need to display a cancel form:
        $view->cancelForm = false;

        // Get held item details:
        $result = $catalog->getMyBookings($patron);
        $recordList = array();
        $this->holds()->resetValidation();
        foreach ($result as $current) {
            // Add cancel details if appropriate:
            $current = $this->holds()->addCancelBookingDetails(
                $catalog, $current, $cancelStatus
            );
            if ($cancelStatus && $cancelStatus['function'] != "getCancelBookingLink"
                && isset($current['cancel_details'])
            ) {
                // Enable cancel form if necessary:
                $view->cancelForm = true;
            }

            $recordList[] = $this->getDriverForILSRecord($current);
        }
        $view->recordList = $recordList;
        return $view;
    }

//                    foreach ($result as $row) {
//                        $record = $this->db->getRecord($row['id']);
//                        $record['ils_details'] = $row;
//                    }

    /**
     * User login action -- clear any previous follow-up information prior to
     * triggering a login process. This is used for explicit login links within
     * the UI to differentiate them from contextual login links that are triggered
     * by attempting to access protected actions.
     *
     * @return mixed
     */
    public function userloginAction()
    {
        // trico edit 2014-08-14 ah: 
        // ATTENTION!!! Submitted as a PR to vufind. 
        // When we pull master, this method can be deltted!!!!
        $this->clearFollowupUrl();
        $config = $this->getConfig();
        if (isset($config->Site->loginToAccount)
            && $config->Site->loginToAccount
        ) {
            $this->followup()->store(array(),
            $this->getServerUrl('myresearch-home'));
        } else {
            $this->setFollowupUrlToReferer();
        }
        if ($si = $this->getSessionInitiator()) {
            return $this->redirect()->toUrl($si);
        }
        return $this->forwardTo('MyResearch', 'Login');
    }

    /**
     * Logout Action
     *
     * @return mixed
     */
    public function logoutAction()
    {
        // instead of sending user back to whatever page they logged in from
        // give them the "logout" screen and redirect to front page after
        // lightbox is closed
        // trico TODO later: vufind now allows a configurable logout target.
        // not the same as our lightbox technique, but worth looking into
        $this->getAuthManager()->logout();
        
        $view = $this->createViewModel();
        return $view;
    }

    /**
     * Login Action
     * trico edit 2014.07.08 ah - add variables to enable context-sensitive
     * links to tripodclassic
     *
     * @return mixed
     */
    public function loginAction()
    {
        $viewmodel = parent::loginAction();
        $viewmodel->setVariables(array(
            'favoritesLink'=>$this->params()->fromPost('favoritesLink', false),
            'holdLink'=>$this->params()->fromPost('holdLink', false),
            'bookingLink'=>$this->params()->fromPost('bookingLink', false)
        ));
        return $viewmodel;
    }

    // trico edit 2014.07.08 ah - add variables to enable context-sensitive
    // links to tripodclassic
    // note: also overridden in RecordController
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

}

