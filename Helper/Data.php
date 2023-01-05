<?php

namespace Flekto\Postcode\Helper;

use Flekto\Postcode\Helper\StoreConfigHelper;
use Flekto\Postcode\Model\Config\Source\NlInputBehavior;
use Flekto\Postcode\Model\Config\Source\ShowHideAddressFields;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Data extends AbstractHelper
{
    /**
     * Constructor
     *
     * @access public
     * @param Context $context
     * @param StoreConfigHelper $storeConfigHelper
     * @return void
     */
    public function __construct(
        Context $context,
        StoreConfigHelper $storeConfigHelper
    ) {
        $this->_storeConfigHelper = $storeConfigHelper;
        parent::__construct($context);
    }

    /**
     * Check if formatted output is disabled.
     *
     * @access public
     * @return bool
     */
    public function isFormattedOutputDisabled() {
        return
            $this->isDisabled()
            || ShowHideAddressFields::FORMAT != $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['show_hide_address_fields']);
    }

    /**
     * Check if Dutch API component is disabled.
     *
     * @access public
     * @return bool
     */
    public function isNlComponentDisabled() {
        return
            $this->isDisabled()
            || false === in_array('NL', $this->_storeConfigHelper->getEnabledCountries())
            || NlInputBehavior::ZIP_HOUSE != $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['nl_input_behavior']);
    }

    /**
     * Check if the module is disabled.
     *
     * @access public
     * @return bool
     */
    public function isDisabled() {
        return
            false === $this->_storeConfigHelper->isSetFlag(StoreConfigHelper::PATH['enabled'])
            || ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE != $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['account_status']);
    }

    /**
     * Check if autofill bypass is disabled.
     *
     * @access public
     * @return bool
     */
    public function isAutofillBypassDisabled() {
        return
            $this->isDisabled()
            || ShowHideAddressFields::SHOW == $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['show_hide_address_fields'])
            || $this->_storeConfigHelper->isSetFlag(StoreConfigHelper::PATH['allow_autofill_bypass']) === false;
    }

}
