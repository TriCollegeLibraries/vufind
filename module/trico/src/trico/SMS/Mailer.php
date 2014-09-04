<?php
/**
 * VuFind Mailer Class for SMS messages
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
* @category VuFind2
 * @package  SMS
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace trico\SMS;
use VuFind\Exception\Mail as MailException;

/**
 * VuFind Mailer Class for SMS messages
 *
 * @category VuFind2
 * @package  SMS
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Mailer extends \VuFind\SMS\Mailer
{
    /**
     * Send a text message to the specified provider.
     *
     * @param string $provider The provider ID to send to
     * @param string $to       The phone number at the provider
     * @param string $from     The email address to use as sender
     * @param string $message  The message to send
     *
     * @throws \VuFind\Exception\Mail
     * @return void
     */
    public function text($provider, $to, $from, $message)
    {
        $knownCarriers = array_keys($this->carriers);
        if (empty($provider) || !in_array($provider, $knownCarriers)) {
            throw new MailException('Unknown Carrier');
        }

        // TRICO edit 2012-05-11 ah
        // someone was getting spammed and we have to block this number.
        // 6102918736
        if (($to == '6102918736') || ($to == '9089300861')) {
            throw new MailException('This has been identified as an erroneous number.  Please contact tricoadmin@brynmawr.edu');
        }

        $to = $this->filterPhoneNumber($to)
            . '@' . $this->carriers[$provider]['domain'];
        $from = empty($from) ? $this->defaultFrom : $from;
        $subject = '';
        return $this->mailer->send($to, $from, $subject, $message);
    }
}
?>
