<?php

/**
 * Factory for the Digital Archives SOLR backend.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace trico\Search\Factory;

/* Do I need to include these since I am extended SolrDefaultBackendFactory? */
/* Should I be extending SolrDefaultBackendFactory? */
use VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory;
use VuFindSearch\Backend\Solr\Connector;

/**
 * Factory for the Digital Archives SOLR backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class DigitalArchivesBackendFactory extends \VuFind\Search\Factory\SolrDefaultBackendFactory
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->searchConfig = 'DigitalArchives';
    }
}
