<?php

namespace trico\ILS\Driver;
use VuFind\Exception\ILS as ILSException;
use Zend\Dom\Query;

class Innovative extends \VuFind\ILS\Driver\Innovative 
{

    // TRICO TODO: 
    //  - look at null returns with an eye to refactoring
    //  - refactor holdings to return an array w/ one less level.
    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        parent::init();
        if (!array_key_exists('url', $this->config['Catalog'])) {
            throw new ILSException("Configuration is missing ['Catalog']['url'].");
        }
        // whether to get holdings data from the expanded holdings page
        $this->expandedHoldingsFlag = false;
        $this->test_url = (array_key_exists('test', $this->config['Catalog'])) && ($this->config['Catalog']['test'])
            ? $this->config['Catalog']['test']
            : $this->config['Catalog']['url'];
        $this->secure_url = (array_key_exists('secure', $this->config['Catalog'])) && ($this->config['Catalog']['secure'])
            ? $this->config['Catalog']['secure']
            : $this->config['Catalog']['url'];
    }

    /**
     * prepID
     *
     * This function returns the correct record id format as defined
     * in the Innovative.ini file.
     *
     * @param string $id ID to format
     *
     * @return string
     */
    protected function prepID($id)
    {
        // TRICO EDIT 2013.07.25 ah
        // our index keeps the '.b' but not the check digit.
        //   this isn't a config option, so we'll adjust it here.
        // trico TODO: either add it as a config option or change our indexing.
        return substr($id, 2);
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig($function)
    {
        if (isset($this->config[$function]) ) {
            $functionConfig = $this->config[$function];
        } else {
            $functionConfig = false;
        }
        return $functionConfig;
    }


    /**
     * trico edit 2013.07.26 ah 
     *  - adding in a querystring param
     *  - adding in cookie param
     *  - changing return value from string (just body) to whole response
     *
     * Make an HTTP request
     *
     * trico TODO: contribute back to vufindhttp module; add cookie handling to get()
     *
     * @param string $url URL to request
     * @param array $params associative array to be used as querystring
     *
     * @return \Zend\Http\Response
     */
    protected function sendRequest($url, $params=array(), $cookies=array())
    {
        $client = $this->httpService->createClient($url);
        // if we let it redirect, we can't get the login cookies
        $client->setOptions(array(
            'maxredirects' => 0,
        ));
        if (!empty($cookies)) {
            $client->addCookie($cookies);
        }
        if (!empty($params)) {
            $client->setParameterGet($params);
        }
        try {
            $response = $client->send();
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        // allow a redirect code; successful logins are redirected
        // trico note: if we're seeing a 301 it's probably because we're not hitting port 443
        if ($response->isSuccess() || ($response->getStatusCode() == 302)) {
            return $response;
        } else {
            throw new ILSException('HTTP error');
        }
    }

    /**
     * Perform a POST request.
     *
     * @param string $url     Request URL
     * @param mixed  $body    Request body document
     * @param string $type    Request body content type
     * @param float  $timeout Request timeout in seconds
     *
     * @return \Zend\Http\Response
     */
    protected function postFormWithCookies($url, $cookies, $params = null, $headers=null, $timeout = null)
    {
        $client = $this->httpService->createClient(
            $url, \Zend\Http\Request::METHOD_POST, $timeout);
        if ($cookies != null) {
            $client->addCookie($cookies);
        }
        if ($params != null) {
            $client->setParameterPost($params);
        }
        if ($headers != null) {
            // headers is an array of key=>val strings representing headers to add
            // (i think this works)
            $client->setHeaders($client->getRequest()->getHeaders()->addHeaders($headers));
        }
        try {
            $result = $client->send();
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        if (!$result->isSuccess()) {
            throw new ILSException('HTTP error');
        }
        return $result;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record. Depending on the expandedHoldingsFlag, 
     * it behaves differently relative to bib records with more than ten items.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        // Strip ID
        $id_ = $this->prepID($id);
        
        // Grab Record Page
        //TRICO edit 2011-09 sl -- adjust URL to query test version of Tripod for less HTML
        try {
            $response = $this->sendRequest(
                $this->test_url . '/record=b' . $id_
            );
        } catch (\Exception $e) {
            // trico TODO: use correct error handling
            return null;
        }
        $result = $response->getBody();

        // establish return array
        $ret = array(
                'bibHoldings' => array(), 
                'items' => array()
            );
        $dummy_barcode = '1234567890123';

        /* TRICO edit 2011-08-31 sl -- 
        test for different bib content - 
        bibItems, bibLinks, bibOrder, and items with no bib content
        */
        $bibLinks = stripos($result, 'bibLinks');
        $bibItems = stripos($result, 'bibItems');
        $bibOrder = stripos($result, 'bibOrder');
        // trico TODO: this is brittle.. breaks if a record has the word "submit" in it -ah
        $submit = stripos($result, 'submit');

        /* TRICO edit 2012-01 sl - Scrape holdings statements for print serials and send to VuFind with most HTML tags remaining.
           Will be inserted as is directly into holdings tab.
        */    
        $bibHoldings = stripos($result, 'bibHoldings');
        if ($bibHoldings) { 
            $r = substr($result, stripos($result, 'bibholdings'));
            $r = substr($r, strpos($r, '>')+1);
            $r = substr($r, 0, stripos($r, "</table"));
            $r = preg_replace('/<a(.*)>Latest Received<\/a>/', "Latest Received", $r);
            $r = preg_replace('/<em.?>/', "", $r);
            $rows = preg_split('/<tr([^>]*)>/', $r);
            $combined = implode("", $rows);
            $ret['bibHoldings'][] = $combined; 
        }

        // Main logic flow of method.
        // TRICO edit 2011-09 sl - test for only bibOrder table (no existing copies in system)
        if ($bibOrder && !$bibItems) {
            // strip out html before the first occurrence of 'bibItems', should be
            // '<table class="bibItems" '
            $r = substr($result, stripos($result, 'bibOrder'));

            // strip out the rest of the first table tag.
            $r = substr($r, strpos($r, '>')+1);

            // strip out the next table closing tag and everything after it.
            $r = substr($r, 0, stripos($r, "</table"));
            // $r should only include the holdings table at this point
            
            // split up into strings that contain each table row, excluding the
            // beginning tr tag.
            $td_rows = preg_split('/<tr([^>]*)>/', $r);
            foreach ($td_rows as $td_row) {
                $td_row = strip_tags($td_row);
                $td_row = trim($td_row);
                if ($td_row == true) {
                    $current = array();
                    $current['id'] = $id;
                    $current['barcode'] = $dummy_barcode;
                    $current['location'] = 'On Order';
                    $current['reserve'] = 'N';
                    $current['callnumber'] = 'On Order';
                    $current['status'] = $td_row;
                    $current['availability'] = 0;
                    $ret['items'][] = $current;
                }
            }

        } elseif ($bibLinks && !$bibItems) {
            //TRICO edit 2011-09 sl - if no bibItems table look for online content and set values
            //note: if bibLinks and bibItems are both present we only scrape bibItems
            $current = array();
            $current['id'] = $id;
            $current['barcode'] = $dummy_barcode;
            $current['location'] = 'Online';
            $current['reserve'] = 'N';
            $current['callnumber'] = 'Online';
            $current['status'] = 'Online';
            $current['availability'] = 1;
            $ret['items'][] = $current;

        } elseif ($submit && !$this->expandedHoldingsFlag) {
            $current = array();
            $current['id'] = $id;
            $current['barcode'] = $dummy_barcode;
            $current['location'] = 'Multiple Locations';
            $current['reserve'] = 'N';
            $current['callnumber'] = 'Multiple Call Numbers';
            $current['status'] = 'Check';
            $current['availability'] = 1;
            $ret['items'][] = $current;

        } elseif ($bibItems) {
            //TRICO edit 2011-09 sl - test for a bibOrder table (a copy on order) on an item record with existing copies (bibItems table)
            if ($bibOrder == true) {
                // strip out html before the first occurrence of 'bibItems', should be
                // '<table class="bibItems" '
                $s = substr($result, stripos($result, 'bibOrder'));

                // strip out the rest of the first table tag.
                $s = substr($s, strpos($s, '>')+1);

                // strip out the next table closing tag and everything after it.
                $s = substr($s, 0, stripos($s, "</table"));

                // split up into strings that contain each table row, excluding the
                // beginning tr tag.
                $td_rows = preg_split('/<tr([^>]*)>/', $s);
                foreach ($td_rows as $td_row) {
                    $td_row = strip_tags($td_row);
                    $td_row = trim($td_row);
                    if ($td_row) {
                        $current = array();
                        $current['id'] = $id;
                        $current['barcode'] = $dummy_barcode;
                        $current['location'] = 'On Order';
                        $current['reserve'] = 'N';
                        $current['callnumber'] = 'On Order';
                        $current['status'] = $td_row;
                        $current['availability'] = 0;
                        $ret['items'][] = $current;
                    }
                }
            }

            /* TRICO edit 2012-01 sl - If there is a submit (>10 copies) button and this is a Bib Record request ($expandedHoldingsFlag = true)
               and not a result set request, scrape the holdings page for holdings data for all items.
               We wait to do this because the holdings page only contains bibItems data. 
            */
            if ($this->expandedHoldingsFlag && $submit) {
                try {
                    $response = $this->sendRequest(
                        $this->test_url . '/search/.b' . $id_ . '/.b' . $id_ .
                        '/1%2C1%2C1%2CB/holdings~' . $id_ . '&FF=&1%2C0%2C'
                    );
                } catch (\Exception $e) {
                    return null;
                }
                $result = $response->getBody();
            }

            // strip out html before the first occurrence of 'bibItems', should be
            // '<table class="bibItems" '
            $r = substr($result, stripos($result, 'bibItems'));
            
            // strip out the rest of the first table tag.
            $r = substr($r, strpos($r, '>')+1);
        
            // strip out the next table closing tag and everything after it.
            $r = substr($r, 0, stripos($r, "</table"));

            // $r should only include the holdings table at this point

            // split up into strings that contain each table row, excluding the
            // beginning tr tag.
            $rows = preg_split('/<tr[^>]*>/', $r);
            // this gets us an array with an empty first element; toss that.
            array_shift($rows);
            $keys = array();

            $loc_col_name      = $this->config['OPAC']['location_column'];
            $call_col_name     = $this->config['OPAC']['call_no_column'];
            $status_col_name   = $this->config['OPAC']['status_column'];

            $firstrow = true;
            foreach ($rows as $row) {
                $current = array();
                // Split up the contents of the row based on the th or td tag, excluding
                // the tags themselves.
                $cols = preg_split('/<t(h|d)([^>]*)>/', $row);
                // get rid of empty first element
                array_shift($cols);
                // go through th, td sections
                for ($i=0; $i < sizeof($cols); $i++) {
                    // replace non blocking space encodings with a space.
                    $cols[$i] = str_replace('&nbsp;', " ", $cols[$i]);
                    // remove html comment tags
                    /*TRICO edit 2011-09-17 sl -- 
                     * commented out the ereg because it slows down processing.
                     * Because the code below uses stripos to match and 
                     * replaces the entire line with a value, not 
                     * really necessary except for dates and they are 
                     * cleaned up below. 
                    */
                    //$cols[$i] = ereg_replace("<!--([^(-->)]*)-->", "", $cols[$i]);
                    // Remove closing th or td tag, trim whitespace and decode 
                    // html entities
                    $cols[$i] = html_entity_decode(
                        trim(substr($cols[$i], 0, stripos($cols[$i], "</t")))
                    );

                    // If this is the first row, it is the header row and has 
                    // the column names
                    if ($firstrow) {
                        $keys[] = $cols[$i];
                    // not the first row, has holding info
                    } else {
                        //look for location column
                        if (stripos($keys[$i], $loc_col_name) > -1) {
                            // TRICO edit 2011-10-25 ah -- get location Url 
                            // have to break this out b/c strpos cld ret false
                            $href_pos = strpos($cols[$i], "href=");
                            if ($href_pos) {
                                $tmp = substr($cols[$i], $href_pos + 6);
                                $current['locUrl'] = substr($tmp, 0, 
                                        strpos($tmp, "\""));
                            }
                            $location = trim(strip_tags($cols[$i]));
                            $current['location'] = $location;

                            //TRICO edit 2011-09-12 ah - reserve, requesting, booking
                            $loc_names   = $this->config['OPAC']['loc_names'];
                            $reserve   = $this->config['OPAC']['reserve'];
                            $norequest   = $this->config['OPAC']['norequest'];
                            $bookable   = $this->config['OPAC']['bookable'];
                            $current['reserve'] = 'N';
                            foreach ($loc_names as $key=>$text) {
                                if (stripos($location, $text) > -1) {
                                    if (array_key_exists($key, $reserve) && $reserve[$key]) {
                                        $current['reserve'] = 'Y';
                                    }
                                    if (array_key_exists($key, $norequest) && $norequest[$key]) {
                                        $current['request_link'] = 'N';
                                    }
                                    if (array_key_exists($key, $bookable) && $bookable[$key]) {
                                        $current['request_link'] = 'B';
                                    }
                                    break;
                                }
                            }
                        }

                        // Does column hold call numbers?
                        if (stripos($keys[$i], $call_col_name) > -1) {
                            $current['callnumber'] = strip_tags($cols[$i]);
                        }
                        // Look for status information.
                        if (stripos($keys[$i], $status_col_name) > -1) {

                            // TRICO edit 2012-01 sl -- Added in test for holds in status line
                            $hold = "";
                            if ($pos = stripos($cols[$i], '+')) {
                                $hold = substr($cols[$i], $pos);
                                //$hold = "and being held for another library patron.";
                            }

                            //TRICO edit 2011-09-12 ah - status message, availability
                            $status   = $this->config['OPAC']['status'];
                            $status_message   = $this->config['OPAC']['status_message'];
                            $availability   = $this->config['OPAC']['availability'];
                            // default values
                            $current['status'] = $status_message['unavailable'];
                            $current['availability'] = 0;

                            foreach ($status as $key=>$text) {
                                if (stripos($cols[$i], $status['due']) > -1) {
                                    /* TRICO edit 2011-08-25 sl -- 
                                     * this status is the only one needing a due 
                                     * date. We have to parse it.
                                    */
                                    $date = substr($cols[$i], 21, 8);
                                    $note = substr($cols[$i], 29);
                                    $current['status'] = 'Due '.$date.' '.$note;
                                    $current['availability'] = 1;
                                    break;
                                } 
                                elseif (stripos($cols[$i], $status['hold']) > -1) {
                                    // another special case. why? can't we just copy the text that would be here?
                                    $current['status'] = substr($cols[$i], 16).' '.'for library patron(s)';
                                    $current['availability'] = 1;
                                }
                                elseif (stripos($cols[$i], $text) > -1) {
                                    if (array_key_exists($key, $status_message)) {
                                        // always insert the hold message if it was found.
                                        $current['status'] = $status_message[$key].' '.$hold;
                                    }
                                    if (array_key_exists($key, $availability)) {
                                        $current['availability'] = $availability[$key];
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
                if (!$firstrow) {
                    $current['id'] = $id;
                    $current['barcode'] = $dummy_barcode;
                    $ret['items'][] = $current;
                }
                $firstrow = false;
            }
        } elseif ($bibHoldings && !$bibItems) {
            //TRICO edit 2011-09 sl - if there's no bib content set values
            $current = array();
            $current['id'] = $id;
            $current['barcode'] = $dummy_barcode;
            $current['location'] = 'Check Holdings';
            $current['reserve'] = 'N';
            $current['callnumber'] = 'Unavailable';
            $current['status'] = 'Check Print Holdings';
            $current['availability'] = 0;
            $ret['items'][] = $current;
        } else {
            //TRICO edit 2011-09 sl - if there's no bib content set values
            $status_message   = $this->config['OPAC']['status_message'];
            $current = array();
            $current['id'] = $id;
            $current['barcode'] = $dummy_barcode;
            $current['location'] = 'Unavailable';
            $current['reserve'] = 'N';
            $current['callnumber'] = 'Unavailable';
            $current['status'] = $status_message['unavailable'];
            $current['availability'] = 0;
            $ret['items'][] = $current;
        }
        // TRICO edit 2014.04.22 ah - each item needs a unique id
        // for use in emailing, texting, etc.
        foreach ($ret['items'] as $key=>$item) {
          $ret['items'][$key]['number'] = $key;
        }
        return $ret;
    }


    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return mixed     An array of getStatus() return values on success,
     * a PEAR_Error object otherwise.
     * @access public
     */
    public function getStatuses($ids)
    {
        $items = array();
        $this->expandedHoldingsFlag = false;
        foreach ($ids as $id) {
            $items[] = $this->getStatus($id);
        }
        return $items;
    }

    /*
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode; on failure, a PEAR_Error.
     * @access public
     */
    public function getHolding($id, array $patron=null)
    {
        $this->expandedHoldingsFlag = true;
        $id = $this->getStatus($id);
        return $id;
    }

    // tricoedit 2013.10.04 ah - made this a wrapper because using patronapi
    // doesn't necessarily mean using pintest.
    // note: the rest of this method would need to be implemented for trico to use it.
    //  (e.g. to allow catalog-based login)
    //  after that, contributing back to vufind would be good.
    /**
     *
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron's password
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        return parent::patronLogin($username, $password);
        // TODO: if username is a barcode, test to make sure it fits proper format
        if ($this->config['PATRONAPI']['enabled'] 
            && $this->config['PATRONAPI']['pintest']) {
            return parent::patronLogin($username, $password);
        } else {
            // TODO: use screen scrape
            return null;
        }
    }

    /**
     * Patron User Link
     *
     * Connects an already-authenticated user (e.g. via shibboleth)
     * to their catalog account.
     * TRICO edit 2012-09-13 ah - new method; this is a different thing than
     * a straight-up authentication:
     * - authentication asks for username / password (barcode & name),
     * - here we ask for barcode and check that the email in vufind account 
     *   matches the one in millennium.
     * This is split up to ensure authentication given that we don't have passwords for millennium.
     *
     * @param string $username The patron barcode
     * @param string $password The patron's last name
     *
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login
     * @access public
     */
    public function patronUserLink($barcode, $email)
    {
        if ($this->config['PATRONAPI']['enabled'] == false) {
            // we don't currently have a course of action here
            return null;
        }
        // use patronAPI to get dump of user data
        $url = $this->config['PATRONAPI']['url'];
        $api_data = $this->dump($url, $barcode, $email);

        // we either got null or we got a patron data array
        if ($api_data == null) return null;

        // return patron info
        // TRICO edit 2012-09-13 ah - many changes here; some of this data
        //   was not needed, but occasional trico-specific edits:
        //   - PATRNNAME holds regular-order name
        //   - PATRNREVR holds last, first
        //   - cat_password is full name which we use instead of pin
        $ret = array();
        $ret['id'] = $api_data['PBARCODE']; // or should I return patron id num?
        $names = explode(',', $api_data['PATRNREVR']);
        $ret['lastname'] = $names[0];
        preg_match(
            "/(.*)\((.*)\)/", $names[1],
            $fnamematch
        );
        $ret['firstname'] = $fnamematch[1];
        $ret['group'] = $fnamematch[2];
        $ret['cat_username'] = urlencode($barcode);
        $ret['cat_password'] = $api_data['PATRNNAME'];
        $ret['email'] = $api_data['EMAILADD'];
        $ret['expiration'] = $api_data['EXPDATE'];
        $ret['address1'] = str_replace("$", ", ", $api_data['ADDRESS']);
        $ret['address2'] = str_replace("$", ", ", $api_data['ADDRESS2']);
        $ret['phone'] = array_key_exists('TELEPHONE', $api_data) ? $api_data['TELEPHONE'] : '';
        return $ret;
    }


    /**
     * dump
     *
     * supports patronUserLink; alternative to _pintest
     *
     * @param string $url patron api url
     * @param string $username The patron barcode
     *
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login
     */
    private function dump($url, $username, $email) 
    {
        // user is valid if email address matches one on file in db
        // if there's no email address 
        $data = $this->parseDump($url, $username);
        if ($data == null) return null;
        if (strtolower($data['EMAILADD']) != strtolower($email)) return null;
        return $data;
    }

    /**
     * parseDump
     *
     * supports dump and _pintest
     * factored out from patronUserLink
     *
     * @param string $url patron api url
     * @param string $username The patron barcode
     *
     * @return mixed           Associative array of patron info on successful dump,
     * null otherwise
     */
    private function parseDump($url, $username) {
        try {
            $response = $this->sendRequest(
                $url . urlencode($username) . '/dump'
            );
        } catch (\Exception $e) {
            return null;
        }
        $result = $response->getBody();

        $api_data = array();
        // The following is taken and modified from patronapi.php by John Blyberg
        // released under the GPL
        $api_contents = trim(strip_tags($result));
        $api_array_lines = explode("\n", $api_contents);
        foreach ($api_array_lines as $api_line) {
            $api_line = str_replace("p=", "peq", $api_line);
            $api_line_arr = explode("=", $api_line);
            $regex_match = array("/\[(.*?)\]/","/\s/","/#/");
            $regex_replace = array('','','NUM');
            $key = trim(
                preg_replace($regex_match, $regex_replace, $api_line_arr[0])
            );
            $api_data[$key] = trim($api_line_arr[1]);
        }

        if (!array_key_exists('PBARCODE', $api_data)) {
            // no barcode found, can look up specific error to return more
            // useful info.  this check needs to be modified to handle using
            // III patron ids also.
            return null;
        }
        return $api_data;
    }

    /**
     * Get Login Link
     *
     * Return a URL to the login page of the ILS OPAC. This is used for 
     * ILSs that do not support an API or method to log in.
     *
     * @return string    URL to ILS's OPAC's place hold screen.
     * @access public
     */
    //public function getLoginLink

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's transactions on success
     * @access public
     */
    public function getMyTransactions($patron)
    {
        // TRICO note ah - since assumption in vufind is that username = barcode
        //   and password = pin (or lastname) and we think of username as 
        //   name and password as barcode, this looks a little backwards 
        //   but is correct.
        $name = $patron['cat_password'];
        $code = $patron['cat_username'];
        try {
            $login_resp = $this->proxyLogin($name, $code);
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        // prepare and submit request
        $userid = $login_resp['userid'];
        try {
            $response = $this->sendRequest(
                "$this->secure_url/patroninfo/$userid/items/?$args_str",
                array('sortByDueDate' => 'byduedate'),
                $login_resp['cookies']
            );
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        $body = $response->getBody();
        return $this->parseCheckedOutItems($body, $userid);
    }

    /**
     * Supports getMyTransactions and renewMyItems
     *
     * parses an http response body for item status data
     *
     * @param string $body An http response body
     * @param string $userid userid parsed from login response
     *
     * @return array              Array of item details keyed by item ID
     */
    private function parseCheckedOutItems($body, $userid) 
    {
        $transList = array();
        $dom = new \Zend\Dom\Query($body);
        $rows = $dom->execute('tr.patFuncEntry');

        foreach($rows as $row) {
            $xml = $row->ownerDocument->saveXML($row);
            $dom->setDocumentHtml($xml);
            $trans = array();
            $trans['item_id'] = $dom->execute(
                'td.patFuncMark input[type="checkbox"]')->current()->getAttribute('value');
            $trans['title'] = trim($dom->execute(
                'td.patFuncTitle')->current()->nodeValue);
            $trans['volume'] = trim($dom->execute(
                'td.patFuncTitle label a span.patFuncVol')->current()->nodeValue);
            $url = $dom->execute(
                'td.patFuncTitle label a')->current(); 
            // ILL items have no <a> tag, no bibid, no full record.
            // ($url will be null)
            if ($url !== null) {
                $trans['id'] = $this->extractBib($url->getAttribute('href'));
            }
            else {
                $trans['id'] = 'ILL';
            }
            $trans['barcode'] = trim($dom->execute(
                'td.patFuncBarcode')->current()->nodeValue);
            $status = trim(strtolower($dom->execute(
                'td.patFuncStatus')->current()->nodeValue));
            $duedate = $this->getDueDate($status);
            $trans['duedate'] = $duedate;
            $trans['dueStatus'] = $this->getDueStatus($duedate);
            // in event of renew failure
            $trans['renewError'] = trim($dom->execute(
                'td.patFuncStatus em font')->current()->nodeValue);
            $trans['renewCount'] = trim($dom->execute(
                'td.patFuncStatus span.patFuncRenewCount')->current()->nodeValue);
            // additional message is everything between duedate and renewcount
            $start = strpos($status, $duedate) + strlen($duedate);
            if ($trans['renewCount']) {
                $end = strpos($status, strtolower($trans['renewCount']));
                $moreStatus = trim(substr($status, strpos($status, $duedate) + strlen($duedate), $end-$start));
            }
            else {
                $moreStatus = trim(substr($status, strpos($status, $duedate) + strlen($duedate)));
            }
            $trans['message'] = ucfirst($moreStatus);

            $checkbox_name = $dom->execute(
                'td.patFuncMark input[type="checkbox"]')->current()->getAttribute('name');
            $query = http_build_query(array(
                "renewsome" => "TRUE",
                $checkbox_name => $trans['item_id'],
                "sortByDueDate" => "byduedate"
            ));
            // NOTE: need the renew link but don't call it renew_link or it shows up in the UI
            $renewlink = $this->secure_url . "/patroninfo/$userid/items?" . $query;
            $trans['renewlink'] = $renewlink;
            // there's no way to tell whether it's renewable until you try to renew
            $trans['renewable'] = true;

            $transList[$trans['item_id']] = $trans;
        }
        return $transList;
    }

    /**
     * supports getMyTransactions
     */
    private function extractBib($url) {
        // millennium puts these annoying '~S10's on the end of bibids
        $parts = explode('~', $url);
        return '.b' . $this->regexOrNull('/\d+$/', $parts[0], 0);
    }

    /**
     * supports getMyTransactions
     * status holds due date, number of renewals; extract date
     */
    private function getDueDate($status) {
        $dateAndTime = $this->regexOrNull('/\d\d-\d\d-\d\d \d\d:\d\d\w\w/', $status, 0);
        if ($dateAndTime) return $dateAndTime;
        $new_date = $this->regexOrNull('/(\d\d-\d\d-\d\d).*(\d\d-\d\d-\d\d)/', $status, 2);
        if ($new_date) return $new_date;
        return $this->regexOrNull('/\d\d-\d\d-\d\d/', $status, 0);
    }

    /**
     * general support
     */
    private function regexOrNull($regex, $v, $i) {
        if (preg_match($regex, $v, $matches)) {
            return $matches[$i];
        } else {
            return null;
        }
    }

    /**
     * supports getMyTransactions
     */
    private function getDueStatus($date) {
        # date looks like "mm-dd-yy"
        $ddate = substr($date, 6, 2) . substr($date, 0, 2) . substr($date, 3, 2);
        $today = date('y').date('m').date('d');
        //return "$ddate";
        if ($ddate - $today == 0) {
            return 'due';
        }
        if ($ddate - $today < 0) {
            return 'overdue'; 
        }
        return '';
    }


    // Logs in and returns an array of cookie kvps
    // throws ILSException on failure
    // supports all the screenscraping of restricted pages
    private function proxyLogin($name, $code) 
    {
        try {
            $response = $this->sendRequest(
                "$this->secure_url/patroninfo/", 
                array(
                    "name" => $name,
                    "code" => $code,
                    "allowRedirects" => "false"
                )
            );
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        # If there is any body in the response, we failed to login
        if (strlen($response->getBody()) > 0) {
            throw new ILSException("Catalog login failed");
        }

        $loc = $response->getHeaders()->get('location');
        $return_array = array(
            "userid"   => $this->getUseridFromPatronLoc($loc),
            "cookies"  => $response->getCookie()
        );
        return $return_array;
    }

    /**
     * supports login
     */
    private function getUseridFromPatronLoc($patron_loc) 
    {
        if (preg_match('/\/patroninfo~S\d+\/(\d+)/', $patron_loc, $matches)) {
            return $matches[1];
        } else {
            return null;
        }
    }


    /**
     * Get Renew Details
     *
     * In order to renew an item via screenscraping, we need the an item
     * id and renewal link. This returns a parseable string 
     * concatenating those values which is then used as submitted form data 
     * in checkedOut.php. This value is then extracted by RenewMyItems.
     *
     * @param array $trans An array of item data
     *
     * @return string Data for use in a form field
     * @access public
     */
    public function getRenewDetails($trans) {
        // renew_details can't be an array b/c it comes via html form.  so 
        // we should make it a parseable string.  parse on semicolon
        return $trans['item_id'] . ';' . $trans['renewlink'];
    }

    /**
     * Renew My Items
     *
     * Renew a patron's items via screenscraping
     * The data in $info['details'] is determined by getRenewDetails().
     *
     * @param array $info An array of data required for renewing items
     * in particular item_id numbers and renew links 
     *
     * @return array              An array of renewal information keyed by item ID
     * or false on failure
     * @access public
     */
    public function renewMyItems($info) 
    {
        // variable setup
        $retarr = array();  // holds array 'block' with general message/s,
                            // array 'details' with indiv. record info.
        $renewResults = array();

        // get login cookies
        $name = $info['patron']['cat_password'];
        $code = $info['patron']['cat_username'];
        try {
            $login_resp = $this->proxyLogin($name, $code);
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        $userid = $login_resp['userid'];

        // perform renewals one at a time
        foreach ($info['details'] as $renewinfo) {
            // vars
            $info_arr = explode(';', $renewinfo);
            $item_id = $info_arr[0];
            $renew_link = $info_arr[1];

            // get html

            // handle 'record in use' race condition
            //   If the requests start too soon after login, you may get 
            //   "Your record is in use by system. Please try again later."
            //   when you try to submit the renewal.
            //   but this could also be due to record being in use by staff
            //     in that case, a "renew all" takes a long time :\ b/c this 
            //     loop will run max times for each item to renew
            $in_use = 0;
            $max_in_use = 10;  // i usually don't get more than 4-6
            while ($in_use < $max_in_use) {
                try {
                    $req = $this->sendRequest(
                        $renew_link,
                        null,
                        $login_resp['cookies']
                    );
                } catch (ILSException $e) {
                    // we may have renewed some items already; just set error 
                    // message for this item
                    $renewError = true;
                    $result['sysMessage'] = "Connection error";
                    $renewResults[$item_id] = $result;
                    continue;  // move on to next item in foreach loop
                }
                $body = $req->getBody();
                $inUseMessage = $this->getInUseMessage($body);
                if ($inUseMessage) {
                    $in_use++;
                }
                else {
                    break;
                }
            }
            // renew was not processed
            if ($in_use == $max_in_use) {
                $renewError = true;
                $result['sysMessage'] = $inUseMessage;
                $renewResults[$item_id] = $result;
                continue;  // move on to next item in foreach loop
            }

            // renew was processed -- parse result
            $parsed = $this->parseCheckedOutItems($body, $userid);
            $item = $parsed[$item_id];
            $result = array();
            // if the renew failed, there will be a fail message
            if ($item['renewError']) {
                $renewError = true;
                $result['sysMessage'] = $item['renewError'];
            }
            else {
                $result['success'] = true;
                $result['new_date'] = $item['duedate'];
            }
            $renewResults[$item_id] = $result;
        }

        // return stuff
        if ($renewError) {
            $block = array("Not all renewals were successful. See details below.");
            $retarr['block'] = $block;
        }
        $retarr['details'] = $renewResults;
        return $retarr;
    }

    /**
     * Supports renewMyItems and cancelBookings
     *
     * If it exists, screenscrapes an in-use message from the 
     * renewal results screen
     *
     * @param string $body An http response body
     *
     * @return string        in-use message on success, else empty string
     */
    private function getInUseMessage($body) {
        $in_use_msg = "Your record is in use by system. Please try again later.";
        $in_use_match = strpos($body, $in_use_msg);
        if ($in_use_match === false) {
            return "";
        }
        // let's get the appropriate article adjectives in there...
        return "Your record is in use by the system. Please try again later.";
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's holds
     * @access public
     *
     * TRICO edit 2013-03-27 sl modified to generate an array of arrays 
     * including scraped holds data and scraped ILL requests. ILL requests
     * are on a separate page from holds in classic so a second URL is generated.
     */

    public function getMyHolds($patron)
    {
        $ret_array = array();
        $name = $patron['cat_password'];
        $code = $patron['cat_username'];
        try {
            $login_resp = $this->proxyLogin($name, $code);
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        $userid = $login_resp['userid'];

        // prepare and submit request for holds data
        try {
            $response = $this->sendRequest(
                "$this->secure_url/patroninfo/$userid/holds",
                null,
                $login_resp['cookies']
            );
        } catch (ILSException $e) {
            throw new ILSException($e->getMessage());
        }
        $body = $response->getBody();

        // time to screenscrape
        $dom = new \Zend\Dom\Query($body);
        $rows = $dom->execute('tr.patFuncEntry');
        foreach ($rows as $row) {
            $xml = $row->ownerDocument->saveXML($row);
            $dom->setDocumentHtml($xml);
            $item = array();
            $item['ils_hold'] = true;
            $item['title'] = trim($dom->execute(
                'td.patFuncTitle')->current()->nodeValue);
            $item['volume'] = trim($dom->execute(
                'td.patFuncTitle label a span.patFuncVol')->current()->nodeValue);
            $url = $dom->execute(
                'td.patFuncTitle label a')->current()->getAttribute('href'); 
            if ($url !== '') {
                $item['id'] = $this->extractBib($url);
            }
            $item['status'] = trim($dom->execute(
                'td.patFuncStatus')->current()->nodeValue);
            $item['location'] = trim($dom->execute(
                'td.patFuncPickup')->current()->nodeValue);
            // trico TODO: do we still need this hack?
            $item['expire'] = trim($this->stripWeirdChars(trim(strtolower($dom->execute(
                'td.patFuncCancel')->current()->nodeValue))));
            $ret_array[] = $item;
        }

        // prepare and submit request for ILL request data
        // trico TODO: refactor this into own method.
        try {
            $response = $this->sendRequest(
                "$this->secure_url/patroninfo/$userid/illreqs",
                null,
                $login_resp['cookies']
            );
        } catch (ILSException $e) {
            throw new ILSException($e->getMessage());
        }
        $ill_body = $response->getBody();

        $dom = new \Zend\Dom\Query($ill_body);
        $rows = $dom->execute('tr.patFuncEntry');
        foreach ($rows as $row) {
            $xml = $row->ownerDocument->saveXML($row);
            $dom->setDocumentHtml($xml);
            $item = array();
            $item['ils_hold'] = false;
            $item['title'] = trim($dom->execute(
                'td.patFuncTitle')->current()->nodeValue);
            $item['status'] = trim($dom->execute(
                'td.patFuncStatus')->current()->nodeValue);
            $item['location'] = trim($dom->execute(
                'td.patFuncPickup')->current()->nodeValue);
            $ret_array[] = $item;
        }
        return $ret_array;
    }

    /**
     * Get Patron Bookings
     *
     * This is responsible for retrieving all bookings by a specific patron.
     * uses screenscraping
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's bookings on success, PEAR_Error
     * otherwise.
     * @access public
     */
    // TRICO edit 2012-11 ah - new method
    public function getMyBookings($patron)
    {
        $name = $patron['cat_password'];
        $code = $patron['cat_username'];
        try {
            $login_resp = $this->proxyLogin($name, $code);
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        // prepare and submit request
        $userid = $login_resp['userid'];
        try {
            $response = $this->sendRequest(
                "$this->secure_url/patroninfo/$userid/bookings",
                null,
                $login_resp['cookies']
            );
        } catch (ILSException $e) {
            throw new ILSException($e->getMessage());
        }
        $body = $response->getBody();
        return $this->parseBookedItems($body);
    }

    /**
     * Supports getMyBookings and cancelBookings
     *
     * parses an http response body for booking data
     *
     * @param string $body An http response body
     * @param string $userid userid parsed from login response
     *
     * @return array              Array of details keyed by item ID
     */
    private function parseBookedItems($body) 
    {
        $ret_array = array();
        $dom = new \Zend\Dom\Query($body);
        $rows = $dom->execute('tr.patFuncEntry');
        foreach ($rows as $row) {
            $xml = $row->ownerDocument->saveXML($row);
            $dom->setDocumentHtml($xml);
            $item = array();
            $item['title'] = trim($dom->execute('td.patFuncTitle')->current()->nodeValue);
            $volume = $dom->execute('td.patFuncTitle label a span.patFuncVol');
            if (count($volume) > 0) {
                $item['volume'] = trim($volume->current()->nodeValue);
            }
            $url = $dom->execute('td.patFuncTitle label a')->current()->getAttribute('href'); 
            if ($url !== '') {
                $item['id'] = $this->extractBib($url);
            }
            $item['status'] = trim($this->stripWeirdChars(trim(strtolower($dom->execute('td.patFuncStatus')->current()->nodeValue))));
            $dates = $dom->execute('td.patFuncBookDate');
            $item['bookingstart'] = $dates->current()->nodeValue;
            $item['bookingend'] = $dates->next()->nodeValue;
            $canceldetails = $dom->execute(
                'td.patFuncMark input')->current()->getAttribute('value'); 
            $canceldetailsarray = explode('F', $canceldetails);
            $item['item_id'] = $canceldetailsarray[0];
            $item['canceldetails'] = $canceldetails;
            $item['boxnum'] = $dom->execute(
                'td.patFuncMark input')->current()->getAttribute('id'); 
            $ret_array[$item['item_id']] = $item;
        }
        return $ret_array;
    }

    /**
    * Get Bookable Date Times
    *
    * This is responsible for gettting a list of valid booking days / times
    *
    * @param array $patron      Patron information returned by the patronLogin
    * method.
    * @param array $details     array, contains most of the same values passed to
    * placeHold, minus the patron data.
    *
    * @return array        An array of arrays like year->month->day->hour
    * @access public
    */
    // TRICO edit 2012-10-02 ah - new method; uses screenscraping
    public function getBookableDateTimes($patron, $details)
    {
        $bibid = $details['id'];
        $url = $this->getBookingTimesLink($bibid); // we only want data.
        $startdt = $this->getCurrentDateTimeArray();
        try {
            $response = $this->submitBookingForm($patron, $bibid, $url, '2', $startdt);
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        // grab the data
        $body = $response->getBody();
        $dom = new \Zend\Dom\Query($body);
        return $this->parseAvailability($dom);
    }
    private function parseAvailability($dom)
    {
        $ret_arr = array(
            'whitelist' => array(),
            'blackouts' => array(),
            'timelookup' => array(),
        );
        // check for max bookings error.
        $h1s = $dom->execute('h1');
        if ($h1s->count() > 0) {
            $h1 = $h1s->current()->nodeValue;
            if (preg_match('/Cannot book item/', $h1)) {
                $ret_arr['error'] = $h1;
            }
        }

        $rows = $dom->execute('div.bookingsCalendar > table > tr');
        if ($rows->count() < 1) {
            return $ret_arr;
        }
        $rownum = 0;
        $year = date('Y');

        $prevmonth = $hour = 'Init';
        foreach ($rows as $row) {
            $xml = $row->ownerDocument->saveXML($row);
            $dom->setDocumentHtml($xml);
            $blackout = true;
            $rownum++;
            $tds = $dom->execute('td');
            $i = 0;
            $ampm = "AM";
            foreach ($tds as $td) {
                $i++;
                if ($i == 1) continue;  // day of week
                if ($i == 2) {
                    // it's the date
                    $monthday = trim($td->nodeValue);
                    $split = preg_split("/\s+/", $monthday);
                    $month = ucwords(strtolower($split[0]));
                    $day = isset($split[1]) ? $split[1] : null;

                    // year is probably current year, but might roll over
                    // look for dec / jan rollover.
                    if (($prevmonth == 'Dec') && ($month == 'Jan')) {
                        $year++;
                    }
                }
                else {
                    // it's the hour
                    $class = $td->getAttribute('class');
                    $prevhour = $hour;
                    $hour = $this->stripWeirdChars(trim($td->nodeValue));
                    if (($hour == '12') && ($prevhour == '11')) {
                        $ampm = "PM";
                    }
                    if ($class == "available") {
                        $blackout = false;
                        $lasthour = $hour;
                        $lastampm = $ampm;
                        if (!isset($firsthour)) {
                            $firsthour = $hour;
                            $firstampm = $ampm;
                        }
                    }
                }
            }
            if ($rownum > 1) { // the first row holds help text, etc; we skip it
                if ($blackout) {
                    $ret_arr['blackouts'][] = array(
                        'month' => $month,
                        'day' => $day, 
                        'year' => $year,
                    );
                } else {
                    $firsttime = sprintf("%02d:00 %s", (int)$firsthour, $firstampm);
                    $lasttime = sprintf("%02d:00 %s", (int)$lasthour, $lastampm);
                    $date = sprintf("%s %s, %s", $month, $day, $year);
                    $ret_arr['whitelist'][] = array(
                        'month' => $month,
                        'day' => $day, 
                        'year' => $year,
                        'date' => $date,
                        'firsttime' => $firsttime,
                        'lasttime' => $lasttime,
                    );
                    $ret_arr['timelookup'][$date] = array(
                        //$date => array(
                            'firsttime' => $firsttime,
                            'lasttime' => $lasttime,
                        //)
                    );
                }
            }
            unset($firsthour);
            $prevmonth = $month;
        }
        return $ret_arr;
    }


    /**
    * Get Booking Now
    *
    * new method - TRICO edit 2012-11-09 ah
    * returns array of current date and time elements, for submitting to 
    *  III form
    * probably not a valid time for the form, but that's okay because we just
    *   want to scrape data, not actually submit anything.
    *
    * @return array 
    */
    private function getCurrentDateTimeArray()
    {
        return array(
            'month' => date('m'),
            'day'   => date('d'),
            'year'  => date('Y'),
            'hour'  => date('h'),
            'min'   => date('i'),
            'ampm'  => date('A'),
        );
    }

    /**
     * Create Booking Form Request
     *
     * new method - TRICO edit 2012-11-09 ah
     *
     * does the login and sends all the post params to III
     *
     * @param array $patron The patron array from patronLogin
     * @param string $bibid 
     * @param string $url see getBookingLink for more info.
     * @param string $pageno the iii form's page number to submit
     * @param array $startdt booking start parameters to submit
     * @param string $itemno the item number and radio number delimited by 'n'
     *
     * @return \Zend\Http\Response
     */
    private function submitBookingForm($patron, $bibid, $url, $pageno, $startdt, $itemno=null)
    {
        $code = $patron['cat_username'];
        $name = $patron['cat_password'];
        // login to get cookies
        try {
            $login_resp = $this->proxyLogin($name, $code);
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        $userid = $login_resp['userid'];

        $post_params = array(
            'webbook_pnum' => $userid,
            'webbook_pagen' => $pageno,
        );
        if($startdt) {
            $post_params['webbook_bgn_Month'] = $startdt['month'];
            $post_params['webbook_bgn_Day'] = $startdt['day'];
            $post_params['webbook_bgn_Year'] = $startdt['year'];
            $post_params['webbook_bgn_Hour'] = $startdt['hour'];
            $post_params['webbook_bgn_Min'] = $startdt['min'];
            $post_params['webbook_bgn_AMPM'] = $startdt['ampm'];
        }
        if($itemno) {
            $iteminfo = explode('n', $itemno);
            $post_params['webbook_itemnum'] = $iteminfo[0];  // item number
            $post_params['webbook_item'] = $iteminfo[1];   // checkbox number
        }

        // submit the request
        return $this->postFormWithCookies(
            $url, 
            $login_resp['cookies'], 
            $post_params
        );
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's fines on success, PEAR_Error
     * otherwise.
     * @access public
     */
    public function getMyFines($patron)
    {
        // get login credentials
        $name = $patron['cat_password'];
        $code = $patron['cat_username'];
        try {
            $login_resp = $this->proxyLogin($name, $code);
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        // prepare and submit request
        $userid = $login_resp['userid'];
        try {
            $response = $this->sendRequest(
                "$this->secure_url/patroninfo/$userid/overdues",
                null,
                $login_resp['cookies']
            );
        } catch (ILSException $e) {
            throw new ILSException($e->getMessage());
        }
        $body = $response->getBody();

        $ret_array = array();
        $fine = array();
        $dom = new \Zend\Dom\Query($body);
        $rows = $dom->execute('table#patfunc_main tr');
        foreach ($rows as $row) {
            $xml = $row->ownerDocument->saveXML($row);
            $dom->setDocumentHtml($xml);
            // bad table design means we have to check what's in each row,
            //   accummulating each array
            $class = $row->getAttribute('class');
            if ($class == 'patFuncTitle') {
                // header row; skip it
                continue;
            }
            elseif ($class == 'patFuncFinesTotal') {
                // we've gone through all the titles
                $ret_array[] = $fine;
                break;
            }
            elseif ($class == 'patFuncFinesEntryTitle') {
                // if we hit a title there may be a complete array to save first
                if (count($fine) > 0) {
                    $ret_array[] = $fine;
                    $fine = array();
                }
                // record this one
                $fine['title'] = trim($dom->execute('td.patFuncFinesEntryTitle')->current()->nodeValue);
            }
            elseif ($class == 'patFuncFinesEntryDetail') {
                // get fee amount
                $amnt = trim($dom->execute('td.patFuncFinesDetailAmt')->current()->nodeValue);
                $fine['amount'] = preg_replace('/[^0-9]/', '', $amnt) * 1;
            }
            elseif ($class == 'patFuncFinesDetailDate') {
                // figure out what sort of date we have here.
                $label = trim($dom->execute('td.patFuncFinesDetailDateLabel')->current()->nodeValue);
                $date = trim($dom->execute('td.patFuncFinesDetailDate')->current()->nodeValue);
                switch (strtolower($label)) {
                    case 'date checked out:':
                        $fine['checkout'] = $date;
                        break;
                    case 'date due:':
                        $fine['duedate'] = $date;
                        break;
                    case 'date renewed:':
                    case 'date returned:':
                        $fine['returned'] = $date;
                        break;
                }
            }
        }
        return $ret_array;
    }

     /**
     * Get Pick Up Locations
     *
     * This is responsible for gettting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.  The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     * @access public
     */
    // TRICO edit 2012-10-02 ah - new method; uses screenscraping
    public function getPickupLocations($patron, $holdDetails=false)
    {
        $locations = array();
        if (!$holdDetails) return $locations;

        // we're gonna screenscrape the locations from the III form.
        // prepare request
        $bib_id = $holdDetails['id'];
        $holdlink = $this->getHoldLink($bib_id, $holdDetails);
        try {
            $response = $this->sendRequest(
                $holdlink
            );
        } catch (ILSException $e) {
            throw new ILSException($e->getMessage());
        }
        $body = $response->getBody();

        // grab the data
        $dom = new \Zend\Dom\Query($body);
        $options = $dom->execute('select#locx00 option');
        foreach ($options as $option) {
            $locid = trim($option->getAttribute('value'));
            // let's not carry over the default / display value
            if ($locid != '--') {
                $loc = array(
                    'locationID' => $locid,
                    'locationDisplay' => trim($option->nodeValue)
                );
                $locations[] = $loc;
            }
        }
        return $locations;
    }

     /**
     * Get Requestable Items
     *
     * modeled on Get Pick Up Locations
     * Since in III we can't screenscrape an item id from the bib record page,
     * this is responsible for getting a list of requestable / holdable items.
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Array, contains the same values passed to
     * placeHold, minus the patron data.
     *
     * @return array        An array of associative arrays with itemID and
     * itemDisplay keys
     * @access public
     */
    // TRICO edit 2012-10-02 ah - new method; uses screenscraping
    public function getRequestableItems($patron, $holdDetails, $locations)
    {
        $items = array();
        // do a dummy submit of the first hold form to get the list of items.
        $bibid = $holdDetails['id'];
        $code = $patron['cat_username'];
        $name = $patron['cat_password'];
        $post_params = array(
            'locx00' => $locations[0]['locationID'],
            'inst' => '',  // instructions
            'code' => $code,
            'name' => $name,
            // don't know what this one means but it's required; 
            //   a little "magic number-y" but this is how nciptoolkit
            //   does it so if it's good enough for them...
            'pat_submit' => 'xxx' 
        );

        // login to get cookies
        try {
            $login_resp = $this->proxyLogin($name, $code);
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        // prepare and submit the request
        $url = $this->getHoldLink($bibid, $holdDetails);
        try {
            $response = $this->postFormWithCookies(
                $url, 
                $login_resp['cookies'], 
                $post_params
                // TODO: Fix this!!!
                //new array('Referrer' => $url)
            );
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        // grab the data
        $body = $response->getBody();
        $dom = new \Zend\Dom\Query($body);
        // search for general 'not holdable' error
        $not_holdable = $dom->execute('center > p > font[color="red"]');
        if (count($not_holdable) > 0) {
            $error_message = trim($not_holdable->current()->nodeValue);
            return $error_message;
        }
        // no general error; there should be at least one holdable item!
        $entries = $dom->execute('tr.bibItemsEntry');
        foreach ($entries as $entry) {
            $xml = $entry->ownerDocument->saveXML($entry);
            $dom->setDocumentHtml($xml);
            $tds = $dom->execute('td');
            $itemID = $dom->execute('td input[type="radio"]');
            if (count($itemID) > 0) {
                $itemID = $itemID->current()->getAttribute('value');
            } else {
                // if there's no itemid this one's not holdable
                $itemID = '';
            }
            $i = 0;
            foreach ($tds as $td) {
                if (1 == $i) {
                    $itemLoc = $this->stripWeirdChars(trim($td->nodeValue));
                } elseif (2 == $i) {
                    $itemCall = $this->stripWeirdChars(trim($td->nodeValue));
                } elseif (3 == $i) {
                    $itemStatus = trim($this->stripWeirdChars(trim($td->nodeValue)));
                }
                $i++;
            }
            if ($itemID) {
                $item = array(
                    'itemID' => $itemID,
                    'itemDisplay' => "$itemLoc -- $itemCall -- $itemStatus"
                );
                $items[] = $item;
            }
        }
        // just in case we didn't find any..
        if (count($items) < 1) {
            $items = "Sorry; no requestable items were found";
        }
        return $items;
    }

    /* this is a total hack and i repent. */
    /* okay i think this is what's happening:
     * the site is in utf-8, but doesn't have the right
     * meta tag.  so php interprets it as latin.  
     * in which case, a bunch of characters become undefined.
     * the better fix would be to add in the utf-8 defintion into the html
     * (not sure exactly when)
     */
    private function stripWeirdChars($str) {
        while (ord($str) > 126) {
            $str = substr($str, 1);
        }
        while (ord(substr($str, -1)) > 126) {
            $str = substr($str, 0, -1);
        }
        return $str;
    }


    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details or a PEAR error on failure of support classes
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available) or a
     * PEAR error on failure of support classes
     * @access public
     */
    public function placeHold($holdDetails) {
        $code = $holdDetails['patron']['cat_username'];
        $name = $holdDetails['patron']['cat_password'];
        $bibid = $holdDetails['id'];
        $post_params = array(
            'radio'  => $holdDetails['itemRequested'],
            'locx00' => $holdDetails['pickUpLocation'],
            'inst'   => $holdDetails['comment'],
            'code'   => $code,
            'name'   => $name,
            'pat_submit' => 'xxx' // don't know what this is :/
        );

        // trico TODO: refactor this login / screenstrape stuff to a helper?

        // login to get cookies
        try {
            $login_resp = $this->proxyLogin($name, $code);
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        // prepare and submit the request
        $url = $this->getHoldLink($bibid, $holdDetails);
        try {
            $response = $this->postFormWithCookies(
                $url, 
                $login_resp['cookies'], 
                $post_params
                // TODO: FIx this@!!!
                //new array('Referrer' => $url)
            );
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        $body = $response->getBody();

        // grab the data
        $dom = new \Zend\Dom\Query($body);
        // you'll get an error message here if you try to request 
        // something you already have on hold
        $not_placed = $dom->execute('div.requestResult p font[color="red"]');

        if (count($not_placed) > 0) {
            $ret_array['success'] = false;
            $ret_array['sysMessage'] = $not_placed->current()->nodeValue;
            if ('No requestable items are available' == $ret_array['sysMessage']) {
                $ret_array['sysMessage'] = 'Please select an item';
            }
        }
        else {
            $ret_array['success'] = true;
        }
        return $ret_array;
    }

    /**
     * Place Booking
     *
     * Attempts to place a booking on a particular item and returns
     * an array with result details or a PEAR error on failure of support classes
     *
     * screenscraps the III webopac
     *
     * @param array $details An array of item, form, and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available) or a
     * PEAR error on failure of support classes
     * @access public
     */
    public function placeBooking($details) {
        // TRICO edit 2012-11-19 ah - Note on multi-page form:
        // can't do the same thing we did for holds (submit the 
        // first one with dummy data to get the info from the 
        // second one and roll it into one form) because here you 
        // might submit the first one and be done.
        $formpage = array_key_exists('itemRequested', $details) ? 2 : 1;
        // parse the form data
        $startdt = $this->dateStringsToArray($details['startdate'], $details['starttime']);
        $bibid = $details['id'];
        $url = $this->getBookingLink($bibid);
        // create request
        if ($formpage == 1) {
            // no items; submit the first page of our form to the 2nd page of iii's form
            try {
                $response = $this->submitBookingForm(
                    $details['patron'], $bibid, $url, '2', $startdt);
            } catch (\Exception $e) {
                throw new ILSException($e->getMessage());
            }
        }
        else {
            // we have items; submitting second page
            try {
                $response = $this->submitBookingForm(
                    $details['patron'], $bibid, $url, '3', $startdt, $details['itemRequested']);
            } catch (\Exception $e) {
                throw new ILSException($e->getMessage());
            }
        }

        // grab data
        $body = $response->getBody();
        $dom = new \Zend\Dom\Query($body);

        // on success there's a div called bookingsConfirm
        $confirmed = $dom->execute('div#bookingsConfirm');
        if ($confirmed->count() > 0) {
            // booking successful; we're done.
            $details['success'] = true;
            return $details;
        }

        // no success? error reporting leaves a lot to be desired.
        // possible reasons (too klunky to list all for users...)
        // - booking may be in the past. e.g. if user walked away and then clicked submit later
        // - user may already have a booking for this item
        // - record may be in use by the system.
        $errorMsg = "Booking form submission error. Please try again or ask at circulation.";
        //   we got either items or availability without explanation
        $items = $this->parseBookingsItems($dom);
        if (!empty($items)) {
            $details['items'] = $items;
            if ($formpage == 2) {
                // we submitted items and got items back
                $details['sysMessage'] = $errorMsg;
            }
            return $details;
        }

        // trico TODO someday
        // At some point they changed the structure of the table 
        // returned in this case, so the form is now broken. We need an api.
        $availability = $this->parseAvailability($dom);
        if ($availability) {
            $details['availability'] = $availability;
            // we weren't told why we got this message; 
            //   probably invalid/expired time or account open by system
            $details['sysMessage'] = $errorMsg;
            return $details;
        }

        // should never get here...
        throw new ILSException("Unknown booking error");
    }

    /* 
     * supports place booking; keeps it from getting too long.
     */
    private function parseBookingsItems($dom) 
    {
        // get all the rows of class bibItemsEntry
        $rows = $dom->execute('tr.bibItemsEntry');
        $items = array();
        // for each, if there's a radio button it's an item row.
        foreach($rows as $row) {
            $xml = $row->ownerDocument->saveXML($row);
            $dom->setDocumentHtml($xml);
            $tds = $dom->execute('td');
            $class = $tds->item(0)->getAttribute('class');
            // sometimes a row doesn't even hold an item, geez.
            if ($class == "bookingsCurrentBookings") continue;
            $item = array();
            $itemDisplay = "";
            // if all but one item is booked for the requested time,
            //   item is not a form element so data is in hidden inupts
            $hidden = $dom->execute('input[type="hidden"]');
            if ($hidden->length > 0) {
                $itemVal = $hidden->item(0)->getAttribute('value');
                $itemID = $hidden->item(1)->getAttribute('value');
            }
            foreach ($tds as $td) {
                $xml = $td->ownerDocument->saveXML($td);
                $dom->setDocumentHtml($xml);
                // the first td holds the radio button (if it's there).
                $radios = ($dom->execute('input'));
                if ($radios->length > 0) {
                    $itemVal = $radios->item(0)->getAttribute('value');
                    $itemVal = $radios->item(0)->getAttribute('onclick');
                    if (preg_match("/,'(\d+)'\)/", $onclick, $matcharr)) {
                        $itemID = $matcharr[1];
                    }
                }
                else {
                    $itemDisplay .= trim($dom->nodeValue) . ' - ';
                }
            }
            $item['itemID'] = $itemID . "n$itemVal";
            $item['itemDisplay'] = substr($itemDisplay, 0, -3);
            $items[] = $item;
        }
        return $items;
    }


    /**
     * date strings to array
     * converts the date format sent in by bookings form 
     * into the format needed by iii bookings form.
     */
    private function dateStringsToArray($startdate, $starttime)
    {
        //e.g.:
        //Nov 13, 2012
        //03:00 PM
        $months = array('Jan'=>'01','Feb'=>'02', 'Mar'=>'03', 'Apr'=>'04',
            'May'=>'05', 'Jun'=>'06', 'Jul'=>'07', 'Aug'=>'08', 'Sep'=>'09',
            'Oct'=>'10', 'Nov'=>'11', 'Dec'=>'12');
        preg_match('/([a-zA-Z]{3}) (\d+), (\d+)/', $startdate, $dmatch);
        preg_match('/(\d+):(\d+) ([AP]M)/', $starttime, $tmatch);
        return array(
            'month' => $months[$dmatch[1]],
            'day' => $dmatch[2],
            'year' => $dmatch[3],
            'hour' => $tmatch[1],
            'min' => $tmatch[2],
            'ampm' => $tmatch[3],
        );
    }

    /**
     * Cancel Bookings
     *
     * Attempts to Cancel a booking on a particular item via screenscraping.
     * The data in $cancelDetails['details'] is determined by 
     * getCancelBookingDetails(); comes through the web form.
     *
     * @param array $info An array of data required for renewing items
     * in particular item_id numbers and renew links 
     *
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     * @access public
     *
     * TRICO edit 2012-11 ah - new method
     */
    public function cancelBookings($cancelDetails)
    {
        // get login cookies
        $name = $cancelDetails['patron']['cat_password'];
        $code = $cancelDetails['patron']['cat_username'];
        try {
            $login_resp = $this->proxyLogin($name, $code);
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        $userid = $login_resp['userid'];
        $url = $this->secure_url . "/patroninfo/$userid/bookings";

        // perform cancelations one at a time
        $successes = 0;
        foreach ($cancelDetails['details'] as $cancelinfo) {
            // vars
            $canceldetailsarray = explode('|', $cancelinfo);
            $item_id = $canceldetailsarray[0];
            $boxnum = $canceldetailsarray[1];
            $canceldetails = $canceldetailsarray[2];
            $post_params = array(
                $boxnum => $canceldetails,
                'canbooksome' => 'canbooksome',
            );

            // submit request while handling 'record in use' race condition
            //   (see longer note under renewMyItems)
            $in_use = 0;
            $max_in_use = 10;
            while ($in_use < $max_in_use) {
                try {
                    $response = $this->postFormWithCookies(
                        $url, 
                        $login_resp['cookies'], 
                        $post_params
                        // TODO: fix this!!!!
                        //new array('Referrer' => $url)
                    );
                } catch (\Exception $e) {
                    // we may have canceled some bookings already; just set error 
                    // message for this item
                    $results[$item_id] = array(
                        'status' => 'Error',
                        'sysMessage' => 'Connection error'
                    );
                    continue 2;  // move on to next item in foreach loop
                }
                $body = $response->getBody();
                $inUseMessage = $this->getInUseMessage($body);
                if ($inUseMessage) {
                    $in_use++;
                }
                else {
                    break;
                }
            }

            // cancellation was not processed
            if ($in_use == $max_in_use) {
                $results[$item_id] = array(
                    'success' => false, 
                    'status' => 'booking_cancel_fail',
                    'sysMessage' => $inUseMessage
                );
                continue;  // move on to next item in foreach loop
            }

            // cancellation was processed -- parse result
            $items = $this->parseBookedItems($body);
            if (in_array($item_id, $items)) {
                // booking was not canceled.
                $results[$item_id] = array(
                    'success' => false, 
                    'status' => 'booking_cancel_fail'
                );
            }
            else {
                // success
                $successes++;
                $results[$item_id] = array(
                    'success' => true,
                    'status' => 'booking_cancel_success'
                );
            }
        }
        $result = array('count' => $successes, 'items' => $results);
        return $result;
    }


    /**
     * Get Cancel Booking Details
     *
     * In order to cancel a booking, III requires the item id, checkbox number, and 
     * string with the item id and booking dates mashed together.  
     * This returns the item id checkbox number, and special string as a string
     * separated by a pipe, which is then submitted as form data in Bookings.php. This
     * value is then extracted by CancelBookings.
     *
     * @param array $details An array of item data
     *
     * @return string Data for use in a form field
     * @access public
     *
     * TRICO edit 2012-11 ah - new method
     */
    public function getCancelBookingDetails($details)
    {
        $cancelDetails = $details['item_id']."|".$details['boxnum']."|".$details['canceldetails'];
        return $cancelDetails;
    }

    /**
     * Get Booking Link and Get Booking Times Link
     *
     * The 2 urls returned can be sent exactly the same 
     * parameters, with the result that the first will submit the form, 
     * while the second will give the form again, with hourly details instead of
     * monthly
     *
     * @param string $id The id of the bib record
     * @param array  $details Item details from getHoldings return array
     *
     * @return string    URL to ILS's OPAC's place booking screen.
     * @access public
     */
    public function getBookingLink($id, $details=false)
    {
        $id = $this->prepID($id);
        return $this->secure_url . "/webbook/b$id&back=record%3Db$id";
    }
    public function getBookingTimesLink($id, $details=false)
    {
        $id = $this->prepID($id);
        $utime = time();
        $link = $this->secure_url . "/webbook/b$id/hourlycal$utime&back=record%3Db$id";
        return $link;
    }

}
