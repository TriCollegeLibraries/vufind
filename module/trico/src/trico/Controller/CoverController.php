<?php

namespace trico\Controller;
use VuFind\Cover\Loader;

class CoverController extends \VuFind\Controller\CoverController
{

    /**
     * Get the cover loader object
     *
     * @return Loader
     */
    protected function getLoader()
    {
        // Construct object for loading cover images if it does not already exist:
        if (!$this->loader) {
            // trico edit 2014.06.17 ah - add a timeout
            $client = $this->getServiceLocator()->get('VuFind\Http')->createClient();
            $client->setOptions(array('timeout' => 3));

            $cacheDir = $this->getServiceLocator()->get('VuFind\CacheManager')
                ->getCache('cover')->getOptions()->getCacheDir();
            $this->loader = new Loader(
                $this->getConfig(),
                $this->getServiceLocator()->get('VuFind\ContentCoversPluginManager'),
                $this->getServiceLocator()->get('VuFindTheme\ThemeInfo'),
                $client,
                $cacheDir
            );
            \VuFind\ServiceManager\Initializer::initInstance(
                $this->loader, $this->getServiceLocator()
            );
        }
        return $this->loader;
    }

}
