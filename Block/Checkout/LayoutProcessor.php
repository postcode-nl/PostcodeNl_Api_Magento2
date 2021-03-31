<?php

namespace Flekto\Postcode\Block\Checkout;

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
     * @param mixed $result
     * @return array
     */
    public function process($result)
    {
        $moduleEnabled = $this->scopeConfig->getValue('postcodenl_api/general/enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if ($moduleEnabled && isset($result['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shipping-address-fieldset'])) {
            $result = $this->processShippingFields($result);
            $result = $this->processBillingFields($result);
        }

        return $result;
    }


    /**
     * processShippingFields function.
     *
     * @access public
     * @param mixed $result
     * @return array
     */
    public function processShippingFields($result)
    {
        $shippingFields = $result['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shipping-address-fieldset']['children'];

        $shippingFields = $this->changeAddressFieldPosition($shippingFields);

        $result['components']['checkout']['children']['steps']['children']
        ['shipping-step']['children']['shippingAddress']['children']
        ['shipping-address-fieldset']['children'] = $shippingFields;

        return $result;
    }


    /**
     * processBillingFields function.
     *
     * @access public
     * @param mixed $result
     * @return array
     */
    public function processBillingFields($result)
    {
        if (isset($result['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['payments-list'])) {

            $paymentForms = $result['components']['checkout']['children']['steps']['children']
            ['billing-step']['children']['payment']['children']
            ['payments-list']['children'];

            foreach ($paymentForms as $paymentMethodForm => $paymentMethodValue) {
                $paymentMethodCode = str_replace('-form', '', $paymentMethodForm);

                if (!isset($result['components']['checkout']['children']['steps']['children']['billing-step']
                    ['children']['payment']['children']['payments-list']['children'][$paymentMethodCode . '-form'])) {
                    continue;
                }

                $billingFields = $result['components']['checkout']['children']['steps']['children']
                ['billing-step']['children']['payment']['children']
                ['payments-list']['children'][$paymentMethodCode . '-form']['children']['form-fields']['children'];

                $shippingFields = $result['components']['checkout']['children']['steps']['children']
                    ['shipping-step']['children']['shippingAddress']['children']['shipping-address-fieldset']['children'];

                $billingFields = array_merge($billingFields, array_intersect_key($shippingFields, ['address_autofill_nl' => 1, 'address_autofill_intl' => 1]));
                $billingFields = $this->changeAddressFieldPosition($billingFields);

                $result['components']['checkout']['children']['steps']['children']['billing-step']
                ['children']['payment']['children']['payments-list']['children'][$paymentMethodCode . '-form']
                ['children']['form-fields']['children'] = $billingFields;
            }
        }

        return $result;
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

        if (isset($addressFields['street'])) {
            $addressFields['street']['sortOrder'] = '930';
        }

        if (isset($addressFields['postcode'])) {
            $addressFields['postcode']['sortOrder'] = '940';
        }

        if (isset($addressFields['city'])) {
            $addressFields['city']['sortOrder'] = '950';
        }

        if (isset($addressFields['region'])) {
            $addressFields['region']['sortOrder'] = '960';
        }

        if (isset($addressFields['region_id'])) {
            $addressFields['region_id']['sortOrder'] = '965';
        }

        return $addressFields;
    }
}
