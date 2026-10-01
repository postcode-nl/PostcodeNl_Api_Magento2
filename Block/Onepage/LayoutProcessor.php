<?php

namespace PostcodeEu\AddressValidation\Block\Onepage;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Helper\Data as DataHelper;

class LayoutProcessor extends AbstractBlock implements LayoutProcessorInterface
{
    /**
     * JS layout configuration
     *
     * @var array
     */
    protected $jsLayout;

    /** @var StoreConfigHelper */
    private $storeConfigHelper;

    /** @var DataHelper */
    private $dataHelper;

    /**
     * Constructor
     *
     * @access public
     * @param Context $context
     * @param StoreConfigHelper $storeConfigHelper
     * @param DataHelper $dataHelper
     * @param array $data (default: [])
     * @return void
     */
    public function __construct(
        Context $context,
        StoreConfigHelper $storeConfigHelper,
        DataHelper $dataHelper,
        array $data = []
    ) {
        $this->storeConfigHelper = $storeConfigHelper;
        $this->dataHelper = $dataHelper;

        parent::__construct($context, $data);
    }

    /**
     * Process Javascript layout of block.
     *
     * @access public
     * @param mixed $jsLayout
     * @return array
     */
    public function process($jsLayout): array
    {
        if ($this->dataHelper->isDisabled()) {
            return $jsLayout;
        }

        $this->jsLayout = $jsLayout;

        // Settings accessible via checkoutProvider.
        $this->jsLayout['components']['checkoutProvider']['postcodeEuConfig'] = $this->storeConfigHelper->getJsInit();

        // Shipping fields. The shipping fieldset is a hard dependency of this integration; unlike the
        // optional billing paths below, a missing one is a hard failure and intentionally throws.
        $shippingFields = &$this->_getJsLayoutRef([
            'components',
            'checkout', 'children',
            'steps', 'children',
            'shipping-step', 'children',
            'shippingAddress', 'children',
            'shipping-address-fieldset', 'children'
        ]);
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
        $billingConfiguration = null;

        try {
            $billingConfiguration = &$this->_getJsLayoutRef([
                'components',
                'checkout', 'children',
                'steps', 'children',
                'billing-step', 'children',
                'payment', 'children',
                'payments-list', 'children',
            ]);
        } catch (LocalizedException $e) {

        }

        if (isset($billingConfiguration)) {
            foreach ($billingConfiguration as $key => &$billingForm) {
                if (false === strpos($key, '-form')) {
                    continue;
                }

                // Make sure form fields exist.
                if (!isset($billingForm['children']['form-fields'])) {
                    continue;
                }

                // Billing fields
                $dataScopePrefix = $billingForm['dataScopePrefix'] ?? null;

                if ($dataScopePrefix === null) {
                    $this->_logger->warning(sprintf(
                        'Billing form "%s" has no dataScopePrefix; skipping autofill fields.',
                        $key
                    ));
                } else {
                    $billingForm['children']['form-fields']['children'] += $this->_updateCustomScope(
                        $autofillFields,
                        $dataScopePrefix,
                    );
                    $billingForm['children']['form-fields']['children'] = $this->_updateDataScope(
                        $billingForm['children']['form-fields']['children'],
                        $dataScopePrefix,
                    );
                }

                $billingForm['children']['form-fields']['children'] = $this->_changeAddressFieldsPosition(
                    $billingForm['children']['form-fields']['children'],
                );
            }
        }

        // Billing address on payment page
        $billingFields = null;

        try {
            $billingFields = &$this->_getJsLayoutRef([
                'components',
                'checkout', 'children',
                'steps', 'children',
                'billing-step', 'children',
                'payment', 'children',
                'afterMethods', 'children',
                'billing-address-form', 'children',
                'form-fields', 'children',
            ]);
        } catch (LocalizedException $e) {

        }

        if (isset($billingFields)) {
            $billingFields += $this->_updateCustomScope($autofillFields, 'billingAddressshared');
            $billingFields = $this->_updateDataScope($billingFields, 'billingAddressshared');
            $billingFields = $this->_changeAddressFieldsPosition($billingFields);
        }

        // Compatibility
        $magePlazaBillingFields = null;

        try {
            $magePlazaBillingFields = &$this->_getJsLayoutRef([
                'components',
                'checkout', 'children',
                'steps', 'children',
                'shipping-step', 'children',
                'billingAddress', 'children',
                'billing-address-fieldset', 'children',
            ]);
        } catch (LocalizedException $e) {

        }

        if (isset($magePlazaBillingFields)) {
            $magePlazaBillingFields += $this->_updateCustomScope($autofillFields, 'billingAddress');
            $magePlazaBillingFields = $this->_updateDataScope($magePlazaBillingFields, 'billingAddress');
            $magePlazaBillingFields = $this->_changeAddressFieldsPosition($magePlazaBillingFields);
        }

        return $this->jsLayout;
    }

    /**
     * Get a reference to the specified path in $this->jsLayout.
     *
     * @param array $path - Path in Javascript layout.
     * @return array - Reference to path in $this->jsLayout.
     * @throws LocalizedException - Throw exception if path wasn't found.
     */
    private function &_getJsLayoutRef(array $path): array
    {
        $result = &$this->jsLayout;
        foreach ($path as $key) {
            if (isset($result[$key])) {
                $result = &$result[$key];
            } else {
                throw new LocalizedException(__('Invalid path in Javascript layout: `%1`', implode('.', $path)));
            }
        }

        return $result;
    }

    /**
     * Find and update customScope
     *
     * @access private
     * @param array $fields
     * @param string $dataScope
     * @return array - Fields with modified customScope.
     */
    private function _updateCustomScope($fields, $dataScope): array
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
     * Find and update dataScope
     *
     * The default dataScope is 'shippingAddress.item-name'. But, it needs to be '$dataScope.item-name' for the billingAddress and billingAddressshared
     *
     * @access private
     * @param array $fields
     * @param string $dataScope
     * @return array - Fields with modified customScope.
     */
    private function _updateDataScope($fields, $dataScope): array
    {
        $shippingAddressScope = 'shippingAddress';

        foreach ($fields as $name => $items) {
            if (isset($items['dataScope'])) {
                $itemDataScope = $items['dataScope'];

                if ($itemDataScope === $shippingAddressScope
                    || strpos($itemDataScope, $shippingAddressScope . '.') === 0
                ) {
                    $fields[$name]['dataScope'] = $dataScope . substr(
                        $itemDataScope,
                        strlen($shippingAddressScope)
                    );
                }
            }

            if (isset($items['children'])) {
                $fields[$name]['children'] = $this->_updateDataScope($items['children'], $dataScope);
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
    private function _changeAddressFieldsPosition($addressFields): array
    {
        if (!$this->storeConfigHelper->isSetFlag('change_fields_position')) {
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
