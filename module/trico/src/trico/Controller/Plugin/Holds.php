<?php
namespace trico\Controller\Plugin;

// trico TODO later: there's now a general AbstractRequestBase. Refactor.
class Holds extends \VuFind\Controller\Plugin\Holds
{

    /**
     * Update ILS details with bookings-cancel information, if appropriate.
     *
     * @param \VuFind\ILS\Connection $catalog      ILS connection object
     * @param array                  $ilsDetails   Hold details from ILS driver's
     * getMyHolds() method
     * @param array                  $cancelStatus Cancel settings from ILS driver's
     * checkFunction() method
     *
     * @return array $ilsDetails with cancellation info added
     */
    public function addCancelBookingDetails($catalog, $ilsDetails, $cancelStatus)
    {
        // Generate Form Details for cancelling Holds if Cancelling Holds
        // is enabled
        if ($cancelStatus) {
            if ($cancelStatus['function'] == "getCancelBookingLink") {
                // Build OPAC URL
                $ilsDetails['cancel_link']
                    = $catalog->getCancelBookingLink($ilsDetails);
            } else {
                // Form Details
                $ilsDetails['cancel_details']
                    = $catalog->getCancelBookingDetails($ilsDetails);
                $this->rememberValidId($ilsDetails['cancel_details']);
            }
        }
        return $ilsDetails;
    }

    /**
     * Private method for cancelling bookings
     *
     * @param array $patron An array of patron information
     *
     * @return null
     * @access private
     */
    public function cancelBookings($catalog, $patron)
    {
        // Retrieve the flashMessenger helper:
        $flashMsg = $this->getController()->flashMessenger();
        $params = $this->getController()->params();

        // Pick IDs to renew based on which button was pressed:
        $all = $params->fromPost('cancelAll');
        $selected = $params->fromPost('cancelSelected');
        if (!empty($all)) {
            $details = $params->fromPost('cancelAllIDS');
        } else if (!empty($selected)) {
            $details = $params->fromPost('cancelSelectedIDS');
        } else {
            // No button pushed -- no action needed
            return array();
        }

        if (!empty($details)) {
            // Confirm? (for non-javascript users)
            if ($params->fromPost('confirm') === "0") {
                if ($params->fromPost('cancelAll') !== null) {
                    return $this->getController()->confirm(
                        'booking_cancel_all',
                        $this->getController()->url()->fromRoute('myresearch-bookings'),
                        $this->getController()->url()->fromRoute('myresearch-bookings'),
                        'confirm_booking_cancel_all_text',
                        array(
                            'cancelAll' => 1,
                            'cancelAllIDS' => $params->fromPost('cancelAllIDS')
                        )
                    );
                } else {
                    return $this->getController()->confirm(
                        'booking_cancel_selected',
                        $this->getController()->url()->fromRoute('myresearch-bookings'),
                        $this->getController()->url()->fromRoute('myresearch-bookings'),
                        'confirm_booking_cancel_selected_text',
                        array(
                            'cancelSelected' => 1,
                            'cancelSelectedIDS' =>
                                $params->fromPost('cancelSelectedIDS')
                        )
                    );
                }
            }

            // trico edit 2014.02.27 ah - we don't need this b/c if 
            // the values aren't allowed, the back end won't accept the booking.
//            foreach ($details as $info) {
//                // If the user input contains a value not found in the session
//                // whitelist, something has been tampered with -- abort the process.
//                if (!in_array($info, $this->getSession()->validIds)) {
//                    $flashMsg->setNamespace('error')
//                        ->addMessage('error_inconsistent_parameters');
//                    return array();
//                }
//            }
//
            // Add Patron Data to Submitted Data
            $cancelResults = $catalog->cancelBookings(
                array('details' => $details, 'patron' => $patron)
            );
            if ($cancelResults == false) {
                $flashMsg->setNamespace('error')->addMessage('booking_cancel_fail');
            } else {
                if ($cancelResults['count'] > 0) {
                    $msg = $this->getController()
                        ->translate('booking_cancel_success_items');
                    $flashMsg->setNamespace('info')->addMessage(
                        $cancelResults['count'] . ' ' . $msg
                    );
                }
                return $cancelResults;
            }
        } else {
             $flashMsg->setNamespace('error')->addMessage('booking_empty_selection');
        }
        return array();
    }
}
