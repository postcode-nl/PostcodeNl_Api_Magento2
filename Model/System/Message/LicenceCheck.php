<?php

namespace Flekto\Postcode\Model\System\Message;

use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;

class LicenceCheck implements MessageInterface
{
    const MESSAGE_IDENTITY = 'flekto_system_message';

    /**
     * @var scopeConfig
     */
    private $scopeConfig;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * __construct function.
     *
     * @access public
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     * @return void
     */
    public function __construct(ScopeConfigInterface $scopeConfig, UrlInterface $urlBuilder)
    {
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
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
     * @return void
     */
    public function isDisplayed()
    {
        $keyIsValid = $this->scopeConfig->getValue('postcodenl_api/general/api_key_is_valid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return !($keyIsValid == 'yes');
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
        $msg .= ' <a href="' . $this->urlBuilder->getUrl('adminhtml/system_config/edit', ['section' => 'postcodenl_api']) . '">' . __('Check your API credentials.') . '</a>';

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
