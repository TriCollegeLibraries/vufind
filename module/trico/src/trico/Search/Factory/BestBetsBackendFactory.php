<?php

/**
 * Factory for the BestBets  backend.
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
use VuFindSearch\Backend\Solr\Connector;

use VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory;

/**
 * Factory for the BestBets backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class BestBetsBackendFactory extends \VuFind\Search\Factory\AbstractSolrBackendFactory
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->solrCore = 'bestbets';
        $this->searchConfig = 'bestbets';
        $this->searchYaml = 'bestbetsspecs.yaml';
    }

    /**
     * Create the SOLR backend.
     *
     * @param Connector $connector Connector
     *
     * @return \VuFindSearch\Backend\Solr\Backend
     */
    protected function createBackend(Connector $connector)
    {
        $backend = parent::createBackend($connector);
        $manager = $this->serviceLocator->get('VuFind\RecordDriverPluginManager');
        $callback = function ($data) use ($manager) {
            $driver = $manager->get('BestBets');
            $driver->setRawData($data);
            return $driver;
        };
        $factory = new RecordCollectionFactory($callback);
        $backend->setRecordCollectionFactory($factory);
        return $backend;
    }
}
