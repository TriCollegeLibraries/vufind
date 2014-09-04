<?php
/**
 * Model for Primo Central records.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace trico\RecordDriver;

/**
 * Model for Primo Central records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class Primo extends \VuFind\RecordDriver\Primo
{
   /**
     * Get an array of all subject headings associated with the record 
     * (may be empty).
     *
     * @return array
     */
    public function getAllSubjectHeadings()
    {
        $subjects = array();
        if (isset($this->fields['subjects'])) {
            $subjects = $this->fields['subjects'];
        }

        return $subjects;
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $retVal = array();

        if (isset($this->fields['url'])) {
            $retVal[] = array();
            $retVal[0]['url'] = $this->fields['url'];
            if (isset($this->fields['fulltext'])) {
                $desc = $this->fields['fulltext'] == 'fulltext'
                    ? 'Get Full Text' : 'Request Full Text in Find It';
                $retVal[0]['desc'] = $this->translate($desc);
            }
            $retVal[0]['value'] = $this->fields['fulltext'];
        }

        return $retVal;
    }

    /** CL - June 2014 - All following methods are added and return empty 
        to avoid Description tab errors 
    */ 

    /**
     * Get background notes on the record.
     *
     * @return array
     */
    public function getBackgroundNotes()
    {
        return array();
    }

    /**
     * Get arrangement notes on the record.
     *
     * @return array
     * @access public
     */
    public function getArrangementNotes()
    { 
        return array();
    }

    /**
     * Get citation on the record.
     *
     * @return array
     * @access public
     */
    public function getMARCCitation()
    {
        return array();
    }

    /**
     * Get references on the record.
     *
     * @return array
     * @access public
     */
    public function getReferences()
    {
        return array();
    }

    /**
     * Get performer on the record. An added Trico method.
     * return empty array to avoid error
     *
     * @return array
     * @access protected
     */
    public function getPerformers()
    {
        return array();
    }

    /**
     * @return array
     * @access public
     */
    public function getMarcAuthorNotes()
    {  
        return array();
    }

    /**
     * Get all alternate titles associated with this record.
     * To facilitate generating hyperlinks from titles
     * subfield codes and data are set as array keys and values
     * and the 'a' and 'b' subfields are combined.
     *
     * @return array
     * @access public
     */
    public function getAlternateTitles()
    {
        return array();
    }
}
