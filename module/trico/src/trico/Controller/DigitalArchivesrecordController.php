<?php
namespace trico\Controller;

class DigitalArchivesrecordController extends RecordController
{
    /**
     * Constructor
     */
    public function __construct(\Zend\Config\Config $config)
    {
        // Override some defaults:
        $this->searchClassId = 'DigitalArchives';

        // Call standard record controller initialization:
        parent::__construct($config);
    }
}
