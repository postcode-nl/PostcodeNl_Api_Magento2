<?php

namespace Flekto\Postcode\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Flekto\Postcode\Model\Config\Source\ShowHideAddressFields;
use Flekto\Postcode\Model\Config\Source\NlInputBehavior;

class Data extends AbstractHelper
{
    public function isFormattedOutputDisabled() {
        return $this->isDisabled() || ShowHideAddressFields::FORMAT != $this->scopeConfig->getValue('postcodenl_api/general/show_hide_address_fields', ScopeInterface::SCOPE_STORE);
    }

    public function isNlComponentDisabled() {
        return $this->isDisabled() || NlInputBehavior::ZIP_HOUSE != $this->scopeConfig->getValue('postcodenl_api/general/nl_input_behavior', ScopeInterface::SCOPE_STORE);
    }

    public function isDisabled() {
        return in_array($this->scopeConfig->getValue('postcodenl_api/general/enabled', ScopeInterface::SCOPE_STORE), ['0', NULL], true);
    }

    public function isAutofillBypassDisabled() {
        return
            $this->isDisabled()
            || ShowHideAddressFields::SHOW == $this->scopeConfig->getValue('postcodenl_api/general/show_hide_address_fields', ScopeInterface::SCOPE_STORE)
            || in_array($this->scopeConfig->getValue('postcodenl_api/general/allow_autofill_bypass', ScopeInterface::SCOPE_STORE), ['0', NULL], true);
    }

}
