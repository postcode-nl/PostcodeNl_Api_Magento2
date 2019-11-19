<?php

namespace Flekto\Postcode\Model\System\Message;

use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class LicenceCheck implements MessageInterface
{
    const MESSAGE_IDENTITY = 'flekto_system_message';

    /**
     * __construct function.
     *
     * @access public
     * @param ScopeConfigInterface $scopeConfig
     * @return void
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
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
        return __('Your Postcode.nl API licence is invalid');
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
