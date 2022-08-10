<?php

namespace Flekto\Postcode\Helper;

use Flekto\Postcode\Model\Config\Source\NlInputBehavior;
use Flekto\Postcode\Model\Config\Source\ShowHideAddressFields;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    public function isFormattedOutputDisabled() {
        return
            $this->isDisabled()
            || ShowHideAddressFields::FORMAT != $this->scopeConfig->getValue(StoreConfigHelper::PATH['show_hide_address_fields'], ScopeInterface::SCOPE_STORE);
    }

    public function isNlComponentDisabled() {
        return
            $this->isDisabled()
            || NlInputBehavior::ZIP_HOUSE != $this->scopeConfig->getValue(StoreConfigHelper::PATH['nl_input_behavior'], ScopeInterface::SCOPE_STORE);
    }

    public function isDisabled() {
        return
            in_array($this->scopeConfig->getValue('postcodenl_api/general/enabled', ScopeInterface::SCOPE_STORE), ['0', NULL], true)
            || ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE != $this->scopeConfig->getValue(StoreConfigHelper::PATH['account_status'], ScopeInterface::SCOPE_STORE);
    }

    public function isAutofillBypassDisabled() {
        return
            $this->isDisabled()
            || ShowHideAddressFields::SHOW == $this->scopeConfig->getValue(StoreConfigHelper::PATH['show_hide_address_fields'], ScopeInterface::SCOPE_STORE)
            || in_array($this->scopeConfig->getValue(StoreConfigHelper::PATH['allow_autofill_bypass'], ScopeInterface::SCOPE_STORE), ['0', NULL], true);
    }

}
