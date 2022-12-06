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

        if (!$moduleEnabled) {
            return $jsLayout;
        }

        // Shipping fields
        $shippingFields = &$jsLayout['components']
            ['checkout']['children']
            ['steps']['children']
            ['shipping-step']['children']
            ['shippingAddress']['children']
            ['shipping-address-fieldset']['children'];

        $shippingFields = $this->_changeAddressFieldsPosition($shippingFields);

        // Autofill fields copy
        $autofillFields = array_intersect_key(
            $shippingFields,
            [
                'address_autofill_nl' => 1,
                'address_autofill_intl' => 1,
                'address_autofill_formatted_output' => 1,
                'address_autofill_bypass' => 1,
            ]
        );

        // Billing step
        $billingConfiguration = &$jsLayout['components']
            ['checkout']['children']
            ['steps']['children']
            ['billing-step']['children']
            ['payment']['children']
            ['payments-list']['children'];

        if (isset($billingConfiguration)) {
            foreach($billingConfiguration as $key => &$billingForm) {
                if (!strpos($key, '-form')) {
                    continue;
                }

                // Make sure form fields exist.
                if (!isset($billingForm['children']['form-fields'])) {
                    continue;
                }

                // Billing fields
                $billingForm['children']['form-fields']['children'] += $this->_updateCustomScope($autofillFields, $billingForm['dataScopePrefix']);
                $billingForm['children']['form-fields']['children'] = $this->_changeAddressFieldsPosition($billingForm['children']['form-fields']['children']);
            }
        }

        // Billing address on payment page
        $billingFields = &$jsLayout['components']
            ['checkout']['children']
            ['steps']['children']
            ['billing-step']['children']
            ['payment']['children']
            ['afterMethods']['children']
            ['billing-address-form']['children']
            ['form-fields']['children'];

        if (isset($billingFields)) {
            $billingFields += $this->_updateCustomScope($autofillFields, 'billingAddressshared');
            $billingFields = $this->_changeAddressFieldsPosition($billingFields);
        }

        // Compatibility
        $magePlazaBillingFields = &$jsLayout['components']
            ['checkout']['children']
            ['steps']['children']
            ['shipping-step']['children']
            ['billingAddress']['children']
            ['billing-address-fieldset']['children'];

        if (isset($magePlazaBillingFields)) {
            $magePlazaBillingFields += $this->_updateCustomScope($autofillFields, 'billingAddress');
            $magePlazaBillingFields = $this->_changeAddressFieldsPosition($magePlazaBillingFields);
        }

        return $jsLayout;
    }

    /**
     * Find and update customScope
     *
     * @access private
     * @param array $fields
     * @param string $dataScope
     * @return array - Fields with modified customScope.
     */
    private function _updateCustomScope($fields, $dataScope)
    {
        foreach ($fields as $name => $items) {
            if (isset($items['config'], $items['config']['customScope'])) {
                $fields[$name]['config']['customScope'] = $dataScope;
            }

            if (isset($items['children'])) {
                $fields[$name]['children'] = $this->_updateCustomScope($items['children'], $dataScope);
            }
        }

        return $fields;
    }

    /**
     * Change sort order of address fields.
     *
     * @access private
     * @param array $addressFields
     * @return array
     */
    private function _changeAddressFieldsPosition($addressFields)
    {
        if ($this->scopeConfig->getValue('postcodenl_api/general/change_fields_position') != '1') {
            return $addressFields;
        }

        $fieldToSortOrder = [
            'country_id' => '900',
            'address_autofill_intl' => '910',
            'address_autofill_nl' => '920',
            'address_autofill_formatted_output' => '930',
            'address_autofill_bypass' => '935',
            'street' => '940',
            'postcode' => '950',
            'city' => '960',
            'region' => '970',
            'region_id' => '975',
        ];

        foreach ($fieldToSortOrder as $name => $sortOrder) {
            if (isset($addressFields[$name])) {
                $addressFields[$name]['sortOrder'] = $sortOrder;
            }
        }

        return $addressFields;
    }
}
