<?php
return array(
    'extends' => 'bootstrap',
    'css' => array(
        'screen_local.css:screen, projection',
        'print_local.css:print',
        'style.css:screen, projection',
    ),
    'js' => array(
        'topnav.js',
    ),
    'favicon' => 'favicon.png',
    'helpers' => array(
        'factories' => array(
//            'layoutclass' => 'trico\Factory::getLayoutClass',
            'auth' => function($sm) {
                return new \trico\View\Helper\Root\Auth(
                    $sm->getServiceLocator()->get('VuFind\AuthManager')
                );
            },
            'searchbox' => function($sm) {
                $config = $sm->getServiceLocator()->get('VuFind\Config');
                return new \trico\View\Helper\Root\SearchBox(
                    $sm->getServiceLocator()->get('VuFind\SearchOptionsPluginManager'),
                    $config->get('searchbox')->toArray()
                );
            },

        ),
        'invokables' => array(
            'search' => 'trico\View\Helper\Tricobootstrap\Search',
        )
    )
);
