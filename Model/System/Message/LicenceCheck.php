<?php

namespace PostcodeEu\AddressValidation\Model\System\Message;

use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\UrlInterface;

class LicenceCheck implements MessageInterface
{
    public const MESSAGE_IDENTITY = 'postcode_eu_licence_check';

    /**
     * @var StoreConfigHelper
     */
    private $_storeConfigHelper;

    /**
     * @var UrlInterface
     */
    private $_urlBuilder;

    /**
     * Constructor
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
     * Retrieve unique message identity.
     *
     * @access public
     * @return string
     */
    public function getIdentity(): string
    {
        return self::MESSAGE_IDENTITY;
    }

    /**
     * Check whether account status isn't active.
     *
     * @access public
     * @return bool
     */
    public function isDisplayed(): bool
    {
        return $this->_storeConfigHelper->getValue('account_status') != \PostcodeEu\AddressValidation\Helper\ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE;
    }

    /**
     * Retrieve message text.
     *
     * @access public
     * @return string
     */
    public function getText(): string
    {
        $msg = __('Your Postcode.eu API licence is invalid.');
        $msg .= ' <a href="' . $this->_urlBuilder->getUrl('adminhtml/system_config/edit', ['section' => 'postcodenl_api']) . '">' . __('Check your API credentials.') . '</a>';

        return $msg;
    }

    /**
     * Retrieve message severity.
     *
     * @access public
     * @return int
     */
    public function getSeverity(): int
    {
        return self::SEVERITY_MAJOR;
    }
}
