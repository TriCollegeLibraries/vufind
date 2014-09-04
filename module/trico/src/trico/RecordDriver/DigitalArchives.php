<?php
/**
 * Model for Solr web records.
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
 * @package  RecordDrivers
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace trico\RecordDriver;

/**
 * Model for Digital Archives / ContentDM records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class DigitalArchives extends \VuFind\RecordDriver\SolrDefault
{
    /**
     * Constructor
     *
     * @param \Zend\Config\Config $mainConfig     VuFind main configuration (omit for
     * built-in defaults)
     * @param \Zend\Config\Config $recordConfig   Record-specific configuration file
     * (omit to use $mainConfig as $recordConfig)
     * @param \Zend\Config\Config $searchSettings Search-specific configuration file
     */
    public function __construct($mainConfig = null, $recordConfig = null,
        $searchSettings = null
    ) {
        $this->preferredSnippetFields = array('description', 'fulltext');
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return isset($this->fields['title']) ?
            $this->fields['title'] : 'Unknown';
    }

    /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @return array
     * @access prublic
     */
    public function getAllSubjectHeadings()
    {
        $topic = isset($this->fields['topic_facet']) ? $this->fields['topic_facet'] : array();
        return $topic;
    }

    /**
     * Get all institutions associated with this record.  
     *
     * @return array
     * @access protected
     */
    public function getInstitution()
    {
        $institutions = isset($this->fields['institution']) ? $this->fields['institution'] : array();
        return $institutions;
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        return isset($this->fields['creation_date']) ?
            $this->fields['creation_date'] : array();
    }

    /**
     * Get all descriptions associated with this record.  
     *
     * @return array
     * @access protected
     */
    public function getCDMDescription()
    {
        return isset($this->fields['cdm_description']) ? $this->fields['cdm_description'] : array();
    }

    /**
     * Get text that can be displayed to represent this record in
     * breadcrumbs.
     *
     * @return string Breadcrumb text to represent this record.
     */
    public function getBreadcrumb()
    {
        return $this->getTitle();
    }

    /**
     * Get the URL for the current record.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->fields['url'];
    }

    /**
     * Indicate whether export is disabled for a particular format.
     *
     * @param string $format Export format
     *
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function exportDisabled($format)
    {
        // Digital Archives are not export-friendly; disable all formats.
        return true;
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
