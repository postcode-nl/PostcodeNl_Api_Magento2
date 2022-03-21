<?php

namespace Flekto\Postcode\Block\Onepage;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Framework\View\Element\Template\Context;

class LayoutProcessor extends AbstractBlock implements LayoutProcessorInterface
{
    protected $scopeConfig;


    /**
     * __construct function.
     *
     * @access public
     * @param Context $context
     * @param array $data (default: [])
     * @return void
     */
    public function __construct(Context $context, array $data = [])
    {
        $this->scopeConfig = $context->getScopeConfig();

        parent::__construct($context, $data);
    }


    /**
     * process function.
     *
     * @access public
     * @param mixed $jsLayout
     * @return array
     */
    public function process($jsLayout)
    {
        $moduleEnabled = $this->scopeConfig->getValue('postcodenl_api/general/enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if ($moduleEnabled && isset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shipping-address-fieldset'])) {
            $formFields = $this->_getFormFields($jsLayout['components']['checkout']['children']['steps']['children']);

            foreach ($formFields as &$fields) {
                $fields = array_merge($fields, $this->_getAutofillFields($jsLayout));
                $fields = $this->changeAddressFieldPosition($fields);
            }

        }

        return $jsLayout;
    }

    /**
     * Get references to $jsLayout form fields.
     *
     * @access private
     * @param mixed $jsLayout
     * @param array $result - Accumulates form fields.
     * @return array - Array of form fields by reference.
     */
    private function _getFormFields(&$jsLayout, &$result = [])
    {
        foreach ($jsLayout as $name => &$value) {
            if (in_array($name, ['form-fields', 'shipping-address-fieldset'], true)) {
                $result[] = &$value['children'];
            }
            else if (is_array($value)) {
                $this->_getFormFields($value, $result);
            }
        }

        return $result;
    }

    /**
     * Get autofill fields from shipping fieldset.
     *
     * @access private
     * @param mixed $jsLayout
     * @return array
     */
    private function _getAutofillFields($jsLayout)
    {
        $shippingFields = $jsLayout['components']['checkout']['children']['steps']['children']
            ['shipping-step']['children']['shippingAddress']['children']['shipping-address-fieldset']['children'];

        return array_intersect_key($shippingFields, ['address_autofill_nl' => 1, 'address_autofill_intl' => 1, 'address_autofill_formatted_output' => 1]);
    }

    /**
     * changeAddressFieldPosition function.
     *
     * @access public
     * @param mixed $addressFields
     * @return array
     */
    public function changeAddressFieldPosition($addressFields)
    {
        if ($this->scopeConfig->getValue('postcodenl_api/general/change_fields_position') != '1') {
            return $addressFields;
        }

        if (isset($addressFields['country_id'])) {
            $addressFields['country_id']['sortOrder'] = '900';
        }

        if (isset($addressFields['address_autofill_intl'])) {
            $addressFields['address_autofill_intl']['sortOrder'] = '910';
        }

        if (isset($addressFields['address_autofill_nl'])) {
            $addressFields['address_autofill_nl']['sortOrder'] = '920';
        }

        if (isset($addressFields['address_autofill_formatted_output'])) {
            $addressFields['address_autofill_formatted_output']['sortOrder'] = '930';
        }

        if (isset($addressFields['street'])) {
            $addressFields['street']['sortOrder'] = '940';
        }

        if (isset($addressFields['postcode'])) {
            $addressFields['postcode']['sortOrder'] = '950';
        }

        if (isset($addressFields['city'])) {
            $addressFields['city']['sortOrder'] = '960';
        }

        if (isset($addressFields['region'])) {
            $addressFields['region']['sortOrder'] = '970';
        }

        if (isset($addressFields['region_id'])) {
            $addressFields['region_id']['sortOrder'] = '975';
        }

        return $addressFields;
    }
}
