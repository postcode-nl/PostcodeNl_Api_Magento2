<?php

namespace Flekto\Postcode\Model\System\Message;

use Magento\Framework\Notification\MessageInterface;
use Flekto\Postcode\Helper\ApiClientHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;

class SupportedCountries implements MessageInterface
{
    const MESSAGE_IDENTITY = 'flekto_system_message';


    /**
     * __construct function.
     *
     * @access public
     * @param ApiClientHelper $postcodeHelper
     * @param ScopeConfigInterface $scopeConfig
     * @return void
     */
    public function __construct(ApiClientHelper $postcodeHelper, ScopeConfigInterface $scopeConfig)
    {
        $this->postcodeHelper = $postcodeHelper;
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
        $supportedCountries = $this->scopeConfig->getValue('postcodenl_api/general/supported_countries', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $supportedCountries;
    }


    /**
     * getText function.
     *
     * @access public
     * @return void
     */
    public function getText()
    {
        return $this->scopeConfig->getValue('postcodenl_api/general/supported_countries',\Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }


    /**
     * getSeverity function.
     *
     * @access public
     * @return void
     */
    public function getSeverity()
    {
        return self::SEVERITY_NOTICE;
    }
}
