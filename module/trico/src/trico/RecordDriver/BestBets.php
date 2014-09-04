<?php
/**
 * Model for BestBets records.
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
 * Model for BestBets records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class BestBets extends \VuFind\RecordDriver\SolrDefault
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
     * Get the unique id of the record.
     *
     * @return string
     */
    public function getUniqueId()
    {
        return $this->fields['id'];
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->fields['title'];
    }

    /**
     * Get the description associated with this record.  
     *
     * @return string
     */
    public function getBestBetsDescription()
    {
        return $this->fields['description'];
    }

    /**
     * Get the bibID for the current record.
     *
     * @return string
     */
    public function getBibID()
    {
        return $this->fields['bibid'];
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
     * Returns one of three things: a full URL to a thumbnail preview of the record
     * if an image is available in an external system; an array of parameters to
     * send to VuFind's internal cover generator if no fixed URL exists; or false
     * if no thumbnail can be generated.
     *
     * @param string $size Size of thumbnail (small, medium or large -- small is
     * default).
     *
     * @return string|array|bool
     */
    public function getThumbnail($size = 'small')
    {
        return array('size' => $size, 'contenttype' => 'BestBets');
    }
}
