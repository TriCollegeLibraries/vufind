<?php
namespace trico\Module\Configuration;

$config = array(
    'service_manager' => array(
        'factories' => array(
            // trico TODO later: these closures have all been moved into factories
            // in vufind master. doing this may enable a future cache-related
            // performance boost. We can use a single factory class for all
            // our local module classes rather than the multiple ones used
            // in vufind master
            // note also there are factories in the theme config
            'VuFind\ILSConnection' => function ($sm) {
                $catalog = new \trico\ILS\Connection(
                    $sm->get('VuFind\Config')->get('config')->Catalog,
                    $sm->get('VuFind\ILSDriverPluginManager'),
                    $sm->get('VuFind\Config')
                );
                return $catalog->setHoldConfig($sm->get('VuFind\ILSHoldSettings'));
            },
            'VuFind\ILSHoldLogic' => function ($sm) {
                return new \trico\ILS\Logic\Holds(
                    $sm->get('VuFind\AuthManager'), $sm->get('VuFind\ILSConnection'),
                    $sm->get('VuFind\HMAC'), $sm->get('VuFind\Config')->get('config')
                );
            },
            'VuFind\AuthManager' => function ($sm) {
                return new \trico\Auth\Manager(
                    $sm->get('VuFind\Config')->get('config')
                );
            },
            'VuFind\SMS' => function ($sm) {
                $mainConfig = $sm->get('VuFind\Config')->get('config');
                $smsConfig = $sm->get('VuFind\Config')->get('sms');
                $options = array('mailer' => $sm->get('VuFind\Mailer'));
                if (isset($mainConfig->Site->email)) {
                    $options['defaultFrom'] = $mainConfig->Site->email;
                }
                return new \trico\SMS\Mailer($smsConfig, $options);
            },
        ),
    ),
    'vufind' => array(
        'plugin_managers' => array(
            'related' => array(
                'factories' => array(
                    'digitalarchivessimilar' =>function($sm) {
                      return new \trico\Related\DigitalArchivesSimilar($sm->getServiceLocator()->get('VuFind\Search'));
                      },
                ),
            ),
            'autocomplete' => array(
                'factories' => array(
                    'solrcn' =>function ($sm) {
                      return new \trico\Autocomplete\SolrCN($sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager'));
                     },
                ),
            ),
            'search_options' => array(
                'factories' => array(
                    'DigitalArchives' =>function ($sm) {
                        return new \trico\Search\DigitalArchives\Options($sm->getServiceLocator()->get('VuFind\Config'));
                     },
                    'BestBets' =>function ($sm) {
                        return new \trico\Search\BestBets\Options($sm->getServiceLocator()->get('VuFind\Config'));
                     },
                ),
            ),
            'search_params' => array(
                'factories' => array(
                    'DigitalArchives' =>function ($sm) {                        
                        $options = $sm->getServiceLocator()->get('VuFind\SearchOptionsPluginManager')->get('DigitalArchives');
                        return new \trico\Search\DigitalArchives\Params(clone($options), $sm->getServiceLocator()->get('VuFind\Config'));
                     },
                    'BestBets' =>function ($sm) {
                        $options = $sm->getServiceLocator()->get('VuFind\SearchOptionsPluginManager')->get('BestBets');
                        return new \trico\Search\BestBets\Params(clone($options), $sm->getServiceLocator()->get('VuFind\Config'));
                     },
                    'Primo' =>function ($sm) {
                        $options = $sm->getServiceLocator()->get('VuFind\SearchOptionsPluginManager')->get('Primo');
                        return new \trico\Search\Primo\Params(clone($options), $sm->getServiceLocator()->get('VuFind\Config'));
                     },
                ),
            ),  
            'search_results' => array(
                'factories' => array(                    
                    'DigitalArchives' =>function ($sm) {
                        $params = $sm->getServiceLocator()->get('VuFind\SearchParamsPluginManager')->get('DigitalArchives');
                        return new \trico\Search\DigitalArchives\Results($params);
                     },
                    'BestBets' =>function ($sm) {
                        $params = $sm->getServiceLocator()->get('VuFind\SearchParamsPluginManager')->get('BestBets');
                        return new \trico\Search\BestBets\Results($params);
                     }
                ),
            ),
            'ils_driver' => array(
                'invokables' => array(
                    'innovative' => 'trico\ILS\Driver\Innovative',
                ),
            ),
            'recorddriver' => array(
                'factories' => array(
                    'solrmarc' => function ($sm) {
                        $driver = new \trico\RecordDriver\SolrMarc(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
                            null,
                            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
                        );
                        $driver->attachILS(
                            $sm->getServiceLocator()->get('VuFind\ILSConnection'),
                            $sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
                            $sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
                        );
                        return $driver;
                    },
                    'solrcontentdm' => function ($sm) {
                        $driver = new \trico\RecordDriver\DigitalArchives(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('DigitalArchives'),$sm->getServiceLocator()->get('VuFind\Config')->get('DigitalArchives'),
                            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
                        );
		        return $driver;
                    },
                    'bestbets' => function ($sm) {
                        $driver = new \trico\RecordDriver\BestBets(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
                            null,
                            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
                        );
            return $driver;
                    },
                    'primo' => function ($sm) {
                        $driver = new \trico\RecordDriver\Primo(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
                            null,
                            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
                        );
                        return $driver;
                    },
                    'libguides' => function ($sm) {
                        return new \trico\RecordDriver\LibGuides();
                    },
                ),
            ),
            'search_backend' => array(
                'factories' => array(
                    'DigitalArchives' => 'trico\Search\Factory\DigitalArchivesBackendFactory',
                    'BestBets' => 'trico\Search\Factory\BestBetsBackendFactory'
                ),
            ),
        ),
    ),
    'controllers' => array(
        'factories' => array(
            'record' => function ($sm) {
                return new \trico\Controller\RecordController(
                    $sm->getServiceLocator()->get('VuFind\Config')->get('config')
                );
            },
            'digitalarchivesrecord' => function ($sm) {
                return new \trico\Controller\DigitalArchivesrecordController(
                    $sm->getServiceLocator()->get('VuFind\Config')->get('config')
                );
            },
        ),
        'invokables' => array(
            'ajax' => 'trico\Controller\AjaxController',
            'search' => 'trico\Controller\SearchController',
            'my-research' => 'trico\Controller\MyResearchController',
            'cart' => 'trico\Controller\CartController',
            'cover' => 'trico\Controller\CoverController',
            'digitalarchives' => 'trico\Controller\DigitalArchivesController',  
            'bestbets' => 'trico\Controller\BestBetsController',
       ),
    ),
    'controller_plugins' => array(
        'factories' => array(
            'holds' => function ($sm) {
                return new \trico\Controller\Plugin\Holds(
                    $sm->getServiceLocator()->get('VuFind\HMAC')
                );
            },
        ),
    ),
    'router' => array(
        'routes' => array(
            'search-tips' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/Search/Tips',
                    'defaults' => array(
                        'controller' => 'Search',
                        'action'     => 'Tips',
                    ),
                 ),
              ),
              'digitalarchives-home' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/Digitized/Home',
                    'defaults' => array(
                        'controller' => 'DigitalArchives',
                        'action'     => 'Home',
                    ),
                 ),
              ),
              'digitalarchives-results' => array(
                  'type' => 'Zend\Mvc\Router\Http\Literal',
                  'options' => array(
                      'route'    => '/Digitized/Results',
                      'defaults' => array(
                          'controller' => 'DigitalArchives',
                          'action'     => 'Results',
                      ),
                  ),
              ),
              'digized' => array(
                'type' => 'Zend\Mvc\Router\Http\Regex',
                'options' => array(
                    'regex'    => '/Digitized[/]?',
                    'defaults' => array(
                        'controller' => 'DigitalArchives',
                        'action'     => 'Home',
                    ),
                 'spec'     => '/Digitized/Home'
                 ),
              ),
            'bestbets-results' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/BestBets/Results',
                    'defaults' => array(
                        'controller' => 'BestBets',
                        'action'     => 'Results',
                    ),
                ),
            ),
            // trico edit 2014.02.11 ah - new routes for trico booking functionality
            'myresearch-bookings' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/MyResearch/Bookings',
                    'defaults' => array(
                        'controller' => 'MyResearch',
                        'action'     => 'Bookings',
                    ),
                ),
            ),
            'record-booking' => array(
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/Record/[:id]/Booking',
                    'defaults' => array(
                        'controller' => 'Record',
                        'action'     => 'Booking',
                    ),
                ),
            ),
            'search-help' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/Search/Help',
                    'defaults' => array(
                        'controller' => 'Search',
                        'action'     => 'Help',
                    ),
                ),
            ),
        ),
    ),
);

// Record sub-routes are generally used to access tab plug-ins, but a few
// URLs are hard-coded to specific actions; this array lists those actions.
$nonTabRecordActions = array(
    'AddComment', 'DeleteComment', 'AddTag', 'Save', 'Email', 'SMS', 'Cite',
    'Export', 'RDF', 'Hold', 'BlockedHold', 'Home', 'StorageRetrievalRequest', 'AjaxTab',
    'BlockedStorageRetrievalRequest', 'ILLRequest', 'BlockedILLRequest', 'PDF',
);
// Build record routes for Digitized Archives
// catch-all "tab" route:
$config['router']['routes']['digitalarchivesrecord'] = array(
    'type'    => 'Zend\Mvc\Router\Http\Segment',
    'options' => array(
        'route'    => '/DigitizedArchivesRecord/[:id[/:tab]]',
        'constraints' => array(
            'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
            'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        ),
        'defaults' => array(
            'controller' => 'DigitalArchivesRecord',
            'action'     => 'Home',
        )
    )
);
// special non-tab actions that each need their own route:
foreach ($nonTabRecordActions as $action) {
    $config['router']['routes']['digitalarchivesrecord-' . strtolower($action)] = array(
        'type'    => 'Zend\Mvc\Router\Http\Segment',
        'options' => array(
            'route'    => '/DigitalArchivesRecord/[:id]/' . $action,
            //'route'    => '/DigitizedArchivesRecord/[:id]/' . $action,
            'constraints' => array(
                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
            ),
            'defaults' => array(
                'controller' => 'DigitalArchivesRecord',
                'action'     => $action,
            )
        )
    );
}

// Build record routes for Articles
// catch-all "tab" route:
$config['router']['routes']['primorecord'] = array(
    'type'    => 'Zend\Mvc\Router\Http\Segment',
    'options' => array(
        'route'    => '/ArticlesRecord/[:id[/:tab]]',
        'constraints' => array(
            'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
            'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        ),
        'defaults' => array(
            'controller' => 'PrimoRecord',
            'action'     => 'Home',
        )
    )
);
// special non-tab actions that each need their own route:
//foreach ($nonTabRecordActions as $action) {
//    $config['router']['routes']['primorecord-' . strtolower($action)] = array(
//        'type'    => 'Zend\Mvc\Router\Http\Segment',
//        'options' => array(
//            'route'    => '/ArticlesRecord/[:id]/' . $action,
//            'constraints' => array(
//                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
//                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
//            ),
//            'defaults' => array(
//                'controller' => 'PrimoRecord',
//                'action'     => $action,
//            )
//        )
//    );
//}


// trico edit 2014.06.14 ah - redefine "Browse" as "Explore" to avoid
// confusion with alphabrowse
$browseRoutes = array(
    'Author', 'Dewey', 'Era', 'Genre', 'Home',
    'LCC', 'Region', 'Tag', 'Topic',
  );
foreach ($browseRoutes as $route) {
    $routeName = 'browse-' . strtolower($route);
    $config['router']['routes'][$routeName] = array(
        'type' => 'Zend\Mvc\Router\Http\Literal',
        'options' => array(
            'route'    => '/Explore/' . $route,
            'defaults' => array(
                'controller' => 'Browse',
                'action'     => $route,
            )
        )
    );
}
$config['router']['routes']['explore'] = array(
    'type' => 'Zend\Mvc\Router\Http\Regex',
    'options' => array(
        'regex'    => '/Explore[/]?',
        'defaults' => array(
            'controller' => 'Browse',
            'action'     => 'Home',
        ),
    'spec'     => '/Explore/Home'
    )
);

// trico edit 2014.06.14 ah - redefine "Search" as "Books"
// in certain contexts
$booksRoutes = array(
    'Search/Advanced', 'Search/Home', 'Search/Results',
  );
foreach ($booksRoutes as $route) {
    list($controller, $action) = explode('/', $route);
    $routeName = str_replace('/', '-', strtolower($route));
    $config['router']['routes'][$routeName] = array(
        'type' => 'Zend\Mvc\Router\Http\Literal',
        'options' => array(
            'route'    => '/Books/' . $action,
            'defaults' => array(
                'controller' => $controller,
                'action'     => $action,
            )
        )
    );
}
$config['router']['routes']['books'] = array(
    'type' => 'Zend\Mvc\Router\Http\Regex',
    'options' => array(
        'regex'    => '/Books[/]?',
        'defaults' => array(
            'controller' => 'Search',
            'action'     => 'Home',
        ),
    'spec'     => '/Books/Home'
    )
);

// trico edit 2014.06.14 ah - redefine "Primo" as "Articles"
$articlesRoutes = array(
    'Primo/Advanced', 'Primo/Home', 'Primo/Search',
  );
foreach ($articlesRoutes as $route) {
    list($controller, $action) = explode('/', $route);
    $routeName = str_replace('/', '-', strtolower($route));
    $config['router']['routes'][$routeName] = array(
        'type' => 'Zend\Mvc\Router\Http\Literal',
        'options' => array(
            'route'    => '/Articles/' . $action,
            'defaults' => array(
                'controller' => $controller,
                'action'     => $action,
            )
        )
    );
}
$config['router']['routes']['articles'] = array(
    'type' => 'Zend\Mvc\Router\Http\Regex',
    'options' => array(
        'regex'    => '/Articles[/]?',
        'defaults' => array(
            'controller' => 'Primo',
            'action'     => 'Home',
        ),
    'spec'     => '/Primo/Home'
    )
);

return $config;
