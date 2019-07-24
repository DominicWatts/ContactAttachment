<?php

namespace Xigen\ContactAttachment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\ObjectManagerInterface;

/**
 * Class Data
 * @package Xigen\ContactAttachment\Helper
 */
class Data extends AbstractHelper
{
    /**
     * @type ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
        parent::__construct($context);
    }

    /**
     * @param $ver
     * @param string $operator
     * @return mixed
     */
    public function versionCompare($ver, $operator = '>=')
    {
        $productMetadata = $this->objectManager->get(ProductMetadataInterface::class);
        $version = $productMetadata->getVersion();
        return version_compare($version, $ver, $operator);
    }
}
