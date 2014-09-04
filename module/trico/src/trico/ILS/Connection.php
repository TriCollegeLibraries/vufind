<?php
namespace trico\ILS;

/* trico edit 2014.01.29 ah - adding bookings classes */
class Connection extends \VuFind\ILS\Connection
{
    /**
     * Check Bookings
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Bookings.
     *
     * @param string $functionConfig The Hold configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for placing holds via a form or a URL; on failure, false.
     * @access protected
     */

    protected function checkMethodBookings($functionConfig)
    {
        $response = false;

        if (isset($this->config->bookings_enabled)
            && ($this->config->bookings_enabled == true)
            && $this->checkCapability('placeBooking')
            && isset($functionConfig['HMACKeys'])
        ) {
            $response = array('function' => "placeBooking");
            $response['HMACKeys'] = explode(":", $functionConfig['HMACKeys']);
            if (isset($functionConfig['extraBookingFields'])) {
                $response['extraBookingFields'] = $functionConfig['extraBookingFields'];
            }
        } else if ($this->checkCapability('getBookingLink')) {
            $response = array('function' => 'getBookingLink');
        }
        return $response;
    }

    // TRICO edit 2012-11-21 ah - new method add bookings cancelation
    /**
     * Check Cancel Bookings
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Cancelling Bookings
     *
     * @param string $functionConfig The Cancel Bookings configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for cancelling bookings via a form or a URL;
     * on failure, false.
     * @access protected
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodcancelBookings($functionConfig)
    {
        global $configArray;
        $response = false;

        if (isset($this->config->cancel_bookings_enabled)
            && ($this->config->cancel_bookings_enabled == true)
            && $this->checkCapability('cancelBookings')
        ) {
            $response = array('function' => "cancelBookings");
        } else if ($configArray['Catalog']['cancel_bookings_enabled'] == true
            && method_exists($this->driver, 'getCancelBookingLink')
        ) {
            $response = array('function' => "getCancelBookingLink");
        }
        return $response;
    }

}
