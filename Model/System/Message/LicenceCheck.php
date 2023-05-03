<?php

namespace Flekto\Postcode\Model\System\Message;

use Flekto\Postcode\Helper\StoreConfigHelper;
use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\UrlInterface;

class LicenceCheck implements MessageInterface
{
    const MESSAGE_IDENTITY = 'flekto_system_message';

    /**
     * @var StoreConfigHelper
     */
    private $_storeConfigHelper;

    /**
     * @var UrlInterface
     */
    private $_urlBuilder;

    /**
     * __construct function.
     *
     * @access public
     * @param StoreConfigHelper $storeConfigHelper
     * @param UrlInterface $urlBuilder
     * @return void
     */
    public function __construct(StoreConfigHelper $storeConfigHelper, UrlInterface $urlBuilder)
    {
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_urlBuilder = $urlBuilder;
    }


    /**
     * getIdentity function.
     *
     * @access public
     * @return void
     */
    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }


    /**
     * isDisplayed function.
     *
     * @access public
     * @return bool
     */
    public function isDisplayed()
    {
        return $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['account_status']) != \Flekto\Postcode\Helper\ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE;
    }


    /**
     * getText function.
     *
     * @access public
     * @return void
     */
    public function getText()
    {
        $msg = __('Your Postcode.eu API licence is invalid.');
        $msg .= ' <a href="' . $this->_urlBuilder->getUrl('adminhtml/system_config/edit', ['section' => 'postcodenl_api']) . '">' . __('Check your API credentials.') . '</a>';

        return $msg;
    }


    /**
     * getSeverity function.
     *
     * @access public
     * @return void
     */
    public function getSeverity()
    {
        return self::SEVERITY_MAJOR;
    }
}
