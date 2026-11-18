<?php

namespace PostcodeEu\AddressValidation\Block\Onepage;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Template\Context;
use PostcodeEu\AddressValidation\Helper\Data as DataHelper;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;

class LayoutProcessor extends AbstractBlock implements LayoutProcessorInterface
{
    private const STEPS = 'components/checkout/children/steps/children/';

    private const SHIPPING_FIELDS = self::STEPS
        . 'shipping-step/children/shippingAddress/children/shipping-address-fieldset/children';

    private const PAYMENT_FORMS = self::STEPS . 'billing-step/children/payment/children/payments-list/children';

    private const SHARED_BILLING_FIELDS = self::STEPS
        . 'billing-step/children/payment/children/afterMethods/children/billing-address-form/children/'
        . 'form-fields/children';

    // Mageplaza One Step Checkout compatibility.
    private const MAGEPLAZA_BILLING_FIELDS = self::STEPS
        . 'shipping-step/children/billingAddress/children/billing-address-fieldset/children';

    private const AUTOFILL_FIELD_NAMES = [
        'address_autofill_nl' => 1,
        'address_autofill_intl' => 1,
        'address_autofill_formatted_output' => 1,
        'address_autofill_bypass' => 1,
        'address_autofill_residence' => 1,
    ];

    /** @var StoreConfigHelper */
    private $storeConfigHelper;

    /** @var DataHelper */
    private $dataHelper;

    /** @var ArrayManager */
    private $arrayManager;

    /**
     * Constructor
     *
     * @access public
     * @param Context $context
     * @param StoreConfigHelper $storeConfigHelper
     * @param DataHelper $dataHelper
     * @param ArrayManager $arrayManager
     * @param array $data (default: [])
     * @return void
     */
    public function __construct(
        Context $context,
        StoreConfigHelper $storeConfigHelper,
        DataHelper $dataHelper,
        ArrayManager $arrayManager,
        array $data = []
    ) {
        $this->storeConfigHelper = $storeConfigHelper;
        $this->dataHelper = $dataHelper;
        $this->arrayManager = $arrayManager;

        parent::__construct($context, $data);
    }

    /**
     * Process Javascript layout of block.
     *
     * @access public
     * @param mixed $jsLayout
     * @return array
     * @throws LocalizedException - If the shipping address fieldset is missing.
     */
    public function process($jsLayout): array
    {
        if ($this->dataHelper->isDisabled()) {
            return $jsLayout;
        }

        // Settings accessible via checkoutProvider.
        $jsLayout['components']['checkoutProvider']['postcodeEuConfig'] = $this->storeConfigHelper->getJsInit();

        // Shipping fields. The shipping fieldset is a hard dependency of this integration; unlike the
        // optional billing paths below, a missing one is a hard failure.
        $shippingFields = $this->_getFields(self::SHIPPING_FIELDS, $jsLayout);

        if ($shippingFields === null) {
            throw new LocalizedException(
                __('Invalid path in Javascript layout: `%1`', self::SHIPPING_FIELDS)
            );
        }

        $shippingFields = $this->_changeAddressFieldsPosition($shippingFields);
        $jsLayout = $this->arrayManager->replace(self::SHIPPING_FIELDS, $jsLayout, $shippingFields);
        $autofillFields = array_intersect_key($shippingFields, self::AUTOFILL_FIELD_NAMES);

        // Billing address forms per payment method.
        $paymentForms = $this->_getFields(self::PAYMENT_FORMS, $jsLayout);

        if ($paymentForms !== null) {
            $jsLayout = $this->arrayManager->replace(
                self::PAYMENT_FORMS,
                $jsLayout,
                $this->_addAutofillFieldsToPaymentForms($paymentForms, $autofillFields)
            );
        }

        // Shared billing address form on the payment page, and Mageplaza's billing fieldset.
        $billingFieldsets = [
            self::SHARED_BILLING_FIELDS => 'billingAddressshared',
            self::MAGEPLAZA_BILLING_FIELDS => 'billingAddress',
        ];

        foreach ($billingFieldsets as $path => $dataScope) {
            $fields = $this->_getFields($path, $jsLayout);

            if ($fields !== null) {
                $jsLayout = $this->arrayManager->replace(
                    $path,
                    $jsLayout,
                    $this->_addAutofillFields($fields, $autofillFields, $dataScope)
                );
            }
        }

        return $jsLayout;
    }

    /**
     * Get the fields array at the specified path in the Javascript layout.
     *
     * @access private
     * @param string $path
     * @param array $jsLayout
     * @return array|null
     */
    private function _getFields(string $path, array $jsLayout): ?array
    {
        $fields = $this->arrayManager->get($path, $jsLayout);

        return is_array($fields) ? $fields : null;
    }

    /**
     * Add autofill fields to the billing address form of each payment method.
     *
     * @access private
     * @param array $paymentForms - Children of payments-list
     * @param array $autofillFields
     * @return array - Updated children of payments-list
     */
    private function _addAutofillFieldsToPaymentForms(array $paymentForms, array $autofillFields): array
    {
        foreach ($paymentForms as $key => $form) {
            if (false === strpos($key, '-form') || !isset($form['children']['form-fields']['children'])) {
                continue;
            }

            $fields = $form['children']['form-fields']['children'];
            $dataScopePrefix = $form['dataScopePrefix'] ?? null;

            if ($dataScopePrefix === null) {
                $this->_logger->warning(sprintf(
                    'Billing form "%s" has no dataScopePrefix; skipping autofill fields.',
                    $key
                ));
                $fields = $this->_changeAddressFieldsPosition($fields);
            } else {
                $fields = $this->_addAutofillFields($fields, $autofillFields, $dataScopePrefix);
            }

            $paymentForms[$key]['children']['form-fields']['children'] = $fields;
        }

        return $paymentForms;
    }

    /**
     * Add autofill fields to a billing address fieldset and scope all fields to the billing address.
     *
     * @access private
     * @param array $fields - Billing address fields
     * @param array $autofillFields - Copied from the shipping address fieldset
     * @param string $dataScope - Billing address data scope
     * @return array
     */
    private function _addAutofillFields(array $fields, array $autofillFields, string $dataScope): array
    {
        $fields += $this->_updateCustomScope($autofillFields, $dataScope);
        $fields = $this->_updateDataScope($fields, $dataScope);

        return $this->_changeAddressFieldsPosition($fields);
    }

    /**
     * Find and update customScope
     *
     * @access private
     * @param array $fields
     * @param string $dataScope
     * @return array - Fields with modified customScope
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
     * The default dataScope is `shippingAddress.item-name`.
     * But, it needs to be `$dataScope.item-name` for the billingAddress and billingAddressshared.
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
            'address_autofill_residence' => '936',
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
