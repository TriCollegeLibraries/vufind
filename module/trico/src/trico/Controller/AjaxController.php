<?php
namespace trico\Controller;
use VuFind\Exception\Auth as AuthException;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class AjaxController extends \VuFind\Controller\AjaxController
{

    /**
     * Get Item Statuses
     *
     * This is responsible for printing the holdings information for a
     * collection of records in JSON format.
     *
     * @return \Zend\Http\Response
     * @author Chris Delis <cedelis@uillinois.edu>
     * @author Tuan Nguyen <tuan@yorku.ca>
     */
    protected function getItemStatusesAjax()
    {
        $this->writeSession();  // avoid session write timing bug
        $catalog = $this->getILS();
        $ids = $this->params()->fromQuery('id');
        $results = $catalog->getStatuses($ids);

        if (!is_array($results)) {
            // If getStatuses returned garbage, let's turn it into an empty array
            // to avoid triggering a notice in the foreach loop below.
            $results = array();
        }

        // In order to detect IDs missing from the status response, create an
        // array with a key for every requested ID.  We will clear keys as we
        // encounter IDs in the response -- anything left will be problems that
        // need special handling.
        $missingIds = array_flip($ids);

        // Get access to PHP template renderer for partials:
        $renderer = $this->getViewRenderer();

        // Load messages for response:
        $messages = array(
            'available' => $renderer->render('ajax/status-available.phtml'),
            'unavailable' => $renderer->render('ajax/status-unavailable.phtml'),
            'unknown' => $renderer->render('ajax/status-unknown.phtml')
        );

        // Load callnumber and location settings:
        $config = $this->getConfig();
        $callnumberSetting = isset($config->Item_Status->multiple_call_nos)
            ? $config->Item_Status->multiple_call_nos : 'msg';
        $locationSetting = isset($config->Item_Status->multiple_locations)
            ? $config->Item_Status->multiple_locations : 'msg';
        $showFullStatus = isset($config->Item_Status->show_full_status)
            ? $config->Item_Status->show_full_status : false;

        // Loop through all the status information that came back
        $statuses = array();
        foreach ($results as $recordNumber=>$record) {
            /* TRICO edit 2012-01 sl - Our driver inserts an additional array 
             * level to return bibHoldings and items.  */
            $record = $record['items'];
            // Filter out suppressed locations:
            $record = $this->filterSuppressedLocations($record);

            // Skip empty records:
            if (count($record)) {
                if ($locationSetting == "group") {
                    $current = $this->getItemStatusGroup(
                        $record, $messages, $callnumberSetting
                    );
                } else {
                    $current = $this->getItemStatus(
                        $record, $messages, $locationSetting, $callnumberSetting
                    );
                }
                // If a full status display has been requested, append the HTML:
                if ($showFullStatus) {
                    $current['full_status'] = $renderer->render(
                        'ajax/status-full.phtml', array('statusItems' => $record)
                    );
                }
                $current['record_number'] = array_search($current['id'], $ids);
                $statuses[] = $current;

                // The current ID is not missing -- remove it from the missing list.
                unset($missingIds[$current['id']]);
            }
        }

        // If any IDs were missing, send back appropriate dummy data
        foreach ($missingIds as $missingId => $recordNumber) {
            $statuses[] = array(
                'id'                   => $missingId,
                'availability'         => 'false',
                // TRICO edit 2011-08 ah - more details in status message
                'availability_container' => $messages['unavailable'],
                'availability_message' => 'Unavailable',
                'location'             => $this->translate('Unknown'),
                'locationList'         => false,
                'reserve'              => 'false',
                'reserve_message'      => $this->translate('Not On Reserve'),
                'callnumber'           => '',
                'missing_data'         => true,
                'record_number'        => $recordNumber
            );
        }

        // Done
        return $this->output($statuses, self::STATUS_OK);
    }

    // TRICO edit 2013.09.26 ah - trico does lots of custom status handling,
    //      mostly to account for multiple locations, reserve stati, etc.
    /**
     * Support method for getItemStatuses() -- process a single bibliographic record
     * for location settings other than "group".
     *
     * @param array  $record            Information on items linked to a single bib
     *                                  record
     * @param array  $messages          Custom status HTML
     *                                  (keys = available/unavailable)
     * @param string $locationSetting   The location mode setting used for
     *                                  pickValue()
     * @param string $callnumberSetting The callnumber mode setting used for
     *                                  pickValue()
     *
     * @return array                    Summarized availability information
     */
    protected function getItemStatus($record, $messages, $locationSetting,
        $callnumberSetting
    ) {
        // Summarize call number, location and availability info across all items:
        $callNumbers = $locations = $statuses = $reserves = array();
        $available = false;
        foreach ($record as $info) {
            // trico edit 2013.10.01 ah - deleting all use_unknown_message
            //   code since we're displaying status instead of 
            //   availability_message anyway.

            // Find an available copy
            if ($info['availability']) {
                $available = true;
            }
            // Store call number/location/status info:
            $callNumbers[] = $info['callnumber'];
            $locations[] = $info['location'];
            $statuses[] = $info['status'];
            $reserves[] = $info['reserve'];
        }

        // Determine location string based on findings:
        $location = $this->pickValue(
            $locations, $locationSetting, 'Multiple Locations'
        );

       // TRICO edit 2011-08 ah - don't display a single call number when 
       // there's not usable location information - confuses patrons.
       if ($location == 'Multiple Locations') {
            $callNumber = 'Multiple Call Numbers';
       }
       else{
            // Determine call number string based on findings:
            $callNumber = $this->pickValue(
                $callNumbers, $callnumberSetting, 'Multiple Call Numbers'
            );
        }

        // TRICO edit 2011-08 ah - 
        // Determine availability_message string based on findings:
        $status = $this->pickValue(
            $statuses,
            'msg',
            'Multiple Statuses'
        );
        // TRICO edit 2011-09-13 sl - 
        // Determine reserve_message string based on findings:
        // Normalizes reserve status across copies for display
        $reserve = $this->pickValue(
            $reserves,
            'msg',
            'Mixed Reserve Statuses'
        );

        //TRICO edit 2011-09-13 sl - 
        //if there are multiple statuses, mixed reserve statuses, or
        //if there is a mix of reserve and non-reserve copies,
        //or if there are more than 10 copies and a second HTTP
        //request is required, display "Check Availabilty" in results set
        if ($status == 'Multiple Statuses' || $status == 'Check' || $reserve == 'Mixed Reserve Statuses' || ($location == 'Multiple Locations' && $reserve == 'Y')) {
            $renderer = $this->getViewRenderer();
            $status = $renderer->render(
                'ajax/status-multi-link.phtml', array('id' => $record[0]['id'])
                    );
        }

        //TRICO edit 2011-09-13 sl -
        //If only one location and copy/copies are on reserve, display reserve
        if ($location != 'Multiple Locations' && $reserve == 'Y') {
            $location = $this->translate('on_reserve') . " - " . $location;
            $status= $this->translate('on_reserve') . " - " . $status;
        }

        // Send back the collected details:
        return array(
            'id' => $record[0]['id'],
            'availability' => ($available ? 'true' : 'false'),
            'availability_container' =>
                $messages[$available ? 'available' : 'unavailable'],
            // trico edit 2013.10.01 ah - use status as availability message
            'availability_message' => $status,
            'location' => htmlentities($location, ENT_COMPAT, 'UTF-8'),
            'locationList' => false,
            // trico edit 2013.10.01 ah - we don't use reserve stuff
            //      ; folded in with location
            'reserve' => false,
                //($record[0]['reserve'] == 'Y' ? 'true' : 'false'),
            'reserve_message' => '',
                //$record[0]['reserve'] == 'Y'
                //? $this->translate('on_reserve')
                //: $this->translate('Not On Reserve'),
            'callnumber' => htmlentities($callNumber, ENT_COMPAT, 'UTF-8')
        );
    }
}
