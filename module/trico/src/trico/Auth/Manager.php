<?php

namespace trico\Auth;
use VuFind\Exception\Auth as AuthException, VuFind\Exception\ILS as ILSException,
    Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Wrapper class for handling logged-in user in session.
 */
class Manager extends \VuFind\Auth\Manager
{

    /**
     * Log the current user into the catalog using stored credentials; if this
     * fails, clear the user's stored credentials so they can enter new, corrected
     * ones.
     *
     * Returns associative array of patron data on success, false on failure.
     *
     * @return array|bool
     */
    public function storedCatalogLogin()
    {
        // Do we have a previously cached ILS account?
        if (is_array($this->ilsAccount)) {
            return $this->ilsAccount;
        }

        try {
            $catalog = $this->getILS();
        } catch (ILSException $e) {
            return false;
        }
        $user = $this->isLoggedIn();

        // Fail if no username is found, but allow a missing password (not every ILS
        // requires a password to connect).
        if ($user && isset($user->cat_username) && !empty($user->cat_username)) {
            try {
                // trico edit 2013.10.08 ah - use alternate method to 
                //   match email address rather than check successful login.
                if ($this->config['Authentication']['link_by_email']) {
                    $patron = $catalog->patronUserLink(
                        $user->cat_username, $user->email
                    );
                } else {
                    $patron = $catalog->patronLogin(
                        $user->cat_username, $user->getCatPassword()
                    );
                }
            } catch (ILSException $e) {
                $patron = null;
            }
            if (empty($patron)) {
                // Problem logging in -- clear user credentials so they can be
                // prompted again; perhaps their password has changed in the
                // system!
                $user->clearCredentials();
            } else {
                $this->ilsAccount = $patron;    // cache for future use
                return $patron;
            }
        }

        return false;
    }

    /**
     * Attempt to log in the user to the ILS, and save credentials if it works.
     *
     * @param string $username Catalog username
     * @param string $password Catalog password
     *
     * Returns associative array of patron data on success, false on failure.
     *
     * @return array|bool
     */
    public function newCatalogLogin($username, $password)
    {
        try {
            $catalog = $this->getILS();
            // trico edit 2013.10.08 ah - use alternate method to 
            //   match email address rather than check successful login.
            if ($this->config['Authentication']['link_by_email']) {
                $user = $this->isLoggedIn();
                if ($user) {
                    $result = $catalog->patronUserLink($username, $user->email);
                }
            } else {
                $result = $catalog->patronLogin($username, $password);
            }
        } catch (ILSException $e) {
            return false;
        }
        if ($result) {
            $user = $this->isLoggedIn();
            if ($user) {
                /* trico edit 2013.12.10 ah - use iii password; 
                 * nothing comes from form. */
                $user->saveCredentials($username, $result['cat_password']);
                $this->updateSession($user);
                $this->ilsAccount = $result;    // cache for future use
            }
            return $result;
        }
        return false;
    }

    /*
     * Is the user logged in via ezproxy?
     *
     * @return bool
     */
    public function usingEzProxy() {
        $proxy_ips = $this->config->Authentication->proxy_ips->toArray();
        // The following may be better but we dont' have request here.
        //$current_ip = $request->getServer()->get('REMOTE_ADDR');
        $current_ip = $_SERVER['REMOTE_ADDR'];
        return in_array($current_ip, $proxy_ips, true);
    }

}
