<?php
/**
 * Primo Central Search Parameters
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Search_Primo
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace trico\Search\Primo;
//use VuFindSearch\ParamBag;

/**
 * Primo Central Search Parameters
 *
 * @category VuFind2
 * @package  Search_Primo
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Params extends \VuFind\Search\Primo\Params
{
    /**
     * HACK ALERT!!!
     * This would be cleaner and dryer if accomplished through a 
     * view helper but for expediency sake, it's going here
     * 
     * Determine if user is coming from off campus return 
     *
     * @return bool
     * @access public
     */
    public function isOnCampus()
    {
        $school_ips = array('165.106'=>'BRYNM',
                            '165.82.'=>'HAVERF',
                            '130.58.'=>'SWARTH');

        $ip = substr($_SERVER['REMOTE_ADDR'],0,7);

        return isset($school_ips[$ip]);
    }
}
