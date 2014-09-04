<?php

namespace trico\ILS\Logic;
use VuFind\ILS\Connection as ILSConnection;

class Holds extends \VuFind\ILS\Logic\Holds
{

    /**
     * Support method to rearrange the holdings array for displaying convenience.
     *
     * @param array $holdings An associative array of location => item array
     *
     * @return array          An associative array keyed by location with each
     * entry being an array with 'notes', 'summary' and 'items' keys.  The 'notes'
     * and 'summary' arrays are note/summary information collected from within the
     * items.
     */
    protected function formatHoldings($holdings)
    {
        // trico edit 2013.07.31 ah - we don't currently use any of this
        return $holdings;
    }

    /**
     * Protected method for driver defined holdings
     *
     * trico edit 2013.07.31 ah - our result object is nested deeper than 
     * the default. also, added checkbookings.
     *
     * @param array $result A result set returned from a driver
     * @param string $id     Record ID   
     *
     * @return array A sorted results set
     */
    protected function driverHoldings($result, $id)
    {
        $holdings = array();

        if (count($result['items'])) {
            // Are holds allowed?
            $checkHolds = $this->catalog->checkFunction("Holds", $id);
            // Are bookings allowed?
            $checkBookings = $this->catalog->checkFunction("Bookings", $id);

            foreach ($result['items'] as $copy) {
                $show = !in_array($copy['location'], $this->hideHoldings);
                if ($show) {
                    if ($checkHolds) {
                        // trico TODO 2014.03.11 ah - note this works differently 
                        // than in vufind core. examine to see if we can bring it
                        // back in line.
                        $copy['link'] = $this->getRequestDetails(
                                $copy, $checkHolds['HMACKeys'], 'Hold'
                            );
                        // If we are unsure whether hold options are available,
                        // set a flag so we can check later via AJAX:
                        // (note: don't think trico uses "check")
                        $copy['check'] = false;
                    }
                    if ($checkBookings) {
                        $copy['bookinglink'] = $this->getRequestDetails(
                                $copy, $checkBookings['HMACKeys'], 'Booking'
                            );
                    }
                    // TEST THIS
                    //$holdings[$copy['location']][] = $copy;
                    $groupKey = $this->getHoldingsGroupKey($copy);
                    $holdings[$groupKey][] = $copy;
                }
            }
        }
        $result['items'] = $holdings;
        return $result;
    }

}
