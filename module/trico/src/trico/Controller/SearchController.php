<?php 

namespace trico\Controller;

class SearchController extends \VuFind\Controller\SearchController
{

    /**
     * Tips action
     *
     * @return createViewModel
     */
    public function tipsAction()
    {
        return $this->createViewModel();
    }

    /**
     * Tips action
     *
     * @return createViewModel
     */
    public function helpAction()
    {
        return $this->createViewModel();
    }
}
?>
