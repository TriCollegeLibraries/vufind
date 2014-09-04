<?php
namespace trico\View\Helper\Root;

class Auth extends \VuFind\View\Helper\Root\Auth
{
    public function usingEzProxy() {
        return $this->getManager()->usingEzProxy();
    }
}
