<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Block\Onepage;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Block\Onepage\LayoutProcessor;
use PostcodeEu\AddressValidation\Helper\Data as DataHelper;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use Psr\Log\LoggerInterface;

/**
 * Checkout JS layout transforms for LayoutProcessor.
 */
class LayoutProcessorTest extends TestCase
{
    private const CHECKOUT = 'components.checkout.children.steps.children';
    private const SHIPPING_FIELDS = self::CHECKOUT . '.shipping-step.children.shippingAddress.children.shipping-address-fieldset.children';
    private const PAYMENTS_LIST = self::CHECKOUT . '.billing-step.children.payment.children.payments-list.children';
    private const PAYMENTS_FIELDS = self::PAYMENTS_LIST . '.billing-address-form.children.form-fields.children';
    private const SHARED_FIELDS = self::CHECKOUT . '.billing-step.children.payment.children.afterMethods.children.billing-address-form.children.form-fields.children';
    private const MAGEPLAZA_FIELDS = self::CHECKOUT . '.shipping-step.children.billingAddress.children.billing-address-fieldset.children';

    #[Test]
    public function disabled_module_returns_layout_untouched_without_loading_config(): void
    {
        $dataHelper = $this->createStub(DataHelper::class);
        $dataHelper->method('isDisabled')->willReturn(true);

        $storeConfigHelper = $this->createMock(StoreConfigHelper::class);
        $storeConfigHelper->expects($this->never())->method('getJsinit');

        $processor = new LayoutProcessor($this->createStub(Context::class), $storeConfigHelper, $dataHelper);
        $layout = $this->createLayout();

        $result = $processor->process($layout);

        $this->assertSame($layout, $result);
    }

    #[Test]
    public function enabled_module_injects_postcode_eu_config(): void
    {
        $config = ['enabled' => true, 'apiActions' => ['autocomplete' => '/postcode-eu/autocomplete']];
        $processor = $this->createProcessor($this->createStoreConfigStub(jsInit: $config));

        $result = $processor->process($this->createLayout());

        $this->assertSame($config, $result['components']['checkoutProvider']['postcodeEuConfig']);
    }

    #[Test]
    #[DataProvider('fieldPositionsProvider')]
    public function shipping_field_positions_follow_config_flag(bool $changeFieldsPosition, array $expectedOrders): void
    {
        $processor = $this->createProcessor(
            $this->createStoreConfigStub(changeFieldsPosition: $changeFieldsPosition)
        );

        $result = $processor->process($this->createLayout());
        $fields = $this->valueAt($result, self::SHIPPING_FIELDS);

        foreach ($expectedOrders as $name => $sortOrder) {
            $this->assertSame($sortOrder, $fields[$name]['sortOrder']);
        }
    }

    /**
     * @return array<string, array{bool, array<string, string>}>
     */
    public static function fieldPositionsProvider(): array
    {
        return [
            'configured' => [true, [
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
            ]],
            'not configured' => [false, [
                'country_id' => '10',
                'address_autofill_intl' => '110',
                'address_autofill_nl' => '100',
                'address_autofill_formatted_output' => '120',
                'address_autofill_bypass' => '130',
                'street' => '20',
                'postcode' => '30',
                'city' => '40',
                'region' => '50',
                'region_id' => '60',
            ]],
        ];
    }

    #[Test]
    public function autofill_fields_copied_from_shipping_into_billing_forms(): void
    {
        $processor = $this->createProcessor($this->createStoreConfigStub());

        $result = $processor->process($this->createLayout());

        foreach ([self::PAYMENTS_FIELDS, self::SHARED_FIELDS, self::MAGEPLAZA_FIELDS] as $path) {
            $fields = $this->valueAt($result, $path);

            foreach ($this->autofillFieldNames() as $name) {
                $this->assertNotEmpty($fields[$name]);
            }

            $this->assertArrayHasKey('firstname', $fields);
            $this->assertSame('kept', $fields['address_autofill_bypass']['config']['someOption']);
            $this->assertSame(
                'kept',
                $fields['address_autofill_formatted_output']['children']['plain_field']['config']['someOption']
            );
            $this->assertArrayNotHasKey('street', $fields);
            $this->assertArrayNotHasKey('postcode', $fields);
        }
    }

    #[Test]
    public function data_scope_leaves_fields_without_data_scope_untouched(): void
    {
        $processor = $this->createProcessor($this->createStoreConfigStub());

        $result = $processor->process($this->createLayout());
        $fields = $this->valueAt($result, self::PAYMENTS_FIELDS);

        $this->assertSame(['someOption' => 'kept'], $fields['plain_no_scope']['config']);
        $this->assertArrayNotHasKey('dataScope', $fields['plain_no_scope']);
    }

    #[Test]
    public function custom_scope_rewritten_recursively_for_copied_autofill_fields(): void
    {
        $processor = $this->createProcessor($this->createStoreConfigStub());

        $result = $processor->process($this->createLayout());
        $billingFields = $this->valueAt($result, self::PAYMENTS_FIELDS);
        $shippingFields = $this->valueAt($result, self::SHIPPING_FIELDS);

        $this->assertSame('billingAddress', $billingFields['address_autofill_nl']['config']['customScope']);
        $this->assertSame(
            'billingAddress',
            $billingFields['address_autofill_formatted_output']['children']['nested_field']['config']['customScope']
        );
        $this->assertArrayNotHasKey(
            'customScope',
            $billingFields['address_autofill_formatted_output']['children']['plain_field']['config']
        );
        $this->assertArrayNotHasKey('customScope', $billingFields['address_autofill_bypass']['config']);
        $this->assertSame('kept', $billingFields['address_autofill_bypass']['config']['someOption']);

        $this->assertSame('shippingAddress', $shippingFields['address_autofill_nl']['config']['customScope']);
    }

    #[Test]
    #[DataProvider('billingScopePathProvider')]
    public function data_scope_rewritten_recursively_to_billing_scope(string $path, string $expectedScope): void
    {
        $processor = $this->createProcessor($this->createStoreConfigStub());

        $result = $processor->process($this->createLayout());
        $fields = $this->valueAt($result, $path);

        $this->assertSame($expectedScope . '.firstname', $fields['firstname']['dataScope']);
        $this->assertSame($expectedScope . '.nested', $fields['firstname']['children']['nested']['dataScope']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function billingScopePathProvider(): array
    {
        return [
            'payments list' => [self::PAYMENTS_FIELDS, 'billingAddress'],
            'payment page' => [self::SHARED_FIELDS, 'billingAddressshared'],
            'mageplaza' => [self::MAGEPLAZA_FIELDS, 'billingAddress'],
        ];
    }

    #[Test]
    public function data_scope_only_rewrites_leading_shipping_address(): void
    {
        $processor = $this->createProcessor($this->createStoreConfigStub());

        $result = $processor->process($this->createLayout());
        $fields = $this->valueAt($result, self::PAYMENTS_FIELDS);

        $this->assertSame('foo.shippingAddress.bar', $fields['odd_scope']['dataScope']);
    }

    #[Test]
    public function data_scope_rewrite_requires_path_boundary(): void
    {
        $processor = $this->createProcessor($this->createStoreConfigStub());

        $result = $processor->process($this->createLayout());
        $fields = $this->valueAt($result, self::PAYMENTS_FIELDS);

        $this->assertSame('shippingAddressFoo.bar', $fields['prefix_lookalike']['dataScope']);
        $this->assertSame('billingAddress', $fields['root_scope']['dataScope']);
    }

    #[Test]
    public function billing_form_without_data_scope_prefix_is_left_without_autofill(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $processor = $this->createProcessor($this->createStoreConfigStub(), null, $logger);

        $result = $processor->process($this->createLayout());
        $fields = $this->valueAt($result, self::PAYMENTS_LIST . '.noprefix-form.children.form-fields.children');

        $this->assertArrayHasKey('firstname', $fields);
        $this->assertArrayNotHasKey('address_autofill_nl', $fields);
    }

    #[Test]
    public function non_form_entries_and_forms_without_fields_skipped(): void
    {
        $processor = $this->createProcessor($this->createStoreConfigStub());

        $result = $processor->process($this->createLayout());
        $paymentsChildren = $this->valueAt($result, self::PAYMENTS_LIST);

        $this->assertSame(
            'shippingAddress.ignored',
            $paymentsChildren['checkmo']['children']['form-fields']['children']['ignored']['dataScope']
        );
        $this->assertArrayNotHasKey('children', $paymentsChildren['free-form']);
    }

    #[Test]
    public function absent_billing_paths_do_not_break_shipping_processing(): void
    {
        $processor = $this->createProcessor($this->createStoreConfigStub());
        $layout = $this->nest([
            self::SHIPPING_FIELDS . '.country_id' => ['sortOrder' => '10'],
        ]);

        $result = $processor->process($layout);

        $fields = $this->valueAt($result, self::SHIPPING_FIELDS);
        $this->assertSame('900', $fields['country_id']['sortOrder']);
    }

    #[Test]
    public function missing_shipping_path_raises_localized_exception(): void
    {
        $processor = $this->createProcessor($this->createStub(StoreConfigHelper::class));
        $layout = $this->nest([
            'components.checkout.children.steps.children' => [],
        ]);

        $this->expectException(LocalizedException::class);

        $processor->process($layout);
    }

    /**
     * @return array<int, string>
     */
    private function autofillFieldNames(): array
    {
        return [
            'address_autofill_nl',
            'address_autofill_intl',
            'address_autofill_formatted_output',
            'address_autofill_bypass',
        ];
    }

    private function createProcessor(
        StoreConfigHelper $storeConfigHelper,
        ?DataHelper $dataHelper = null,
        ?LoggerInterface $logger = null
    ): LayoutProcessor {
        $context = $this->createStub(Context::class);
        $context->method('getLogger')->willReturn($logger ?? $this->createStub(LoggerInterface::class));

        return new LayoutProcessor(
            $context,
            $storeConfigHelper,
            $dataHelper ?? $this->createDataHelperStub()
        );
    }

    private function createDataHelperStub(): DataHelper
    {
        $dataHelper = $this->createStub(DataHelper::class);
        $dataHelper->method('isDisabled')->willReturn(false);

        return $dataHelper;
    }

    /**
     * @param array<string, mixed> $jsInit
     */
    private function createStoreConfigStub(
        bool $changeFieldsPosition = true,
        array $jsInit = ['enabled' => true]
    ): StoreConfigHelper {
        $storeConfigHelper = $this->createMock(StoreConfigHelper::class);
        $storeConfigHelper->expects($this->atLeastOnce())
            ->method('isSetFlag')
            ->with('change_fields_position')
            ->willReturn($changeFieldsPosition);
        $storeConfigHelper->method('getJsinit')->willReturn($jsInit);

        return $storeConfigHelper;
    }

    /**
     * @param array<string, mixed> $layout
     * @return array<string, mixed>
     */
    private function valueAt(array $layout, string $path): array
    {
        foreach (explode('.', $path) as $key) {
            $layout = $layout[$key];
        }

        return $layout;
    }

    /**
     * Expand dotted-path leaves into nested arrays, e.g. `'a.b' => 1` becomes `['a' => ['b' => 1]]`.
     *
     * @param array<string, mixed> $leaves - Dotted path => leaf value.
     * @return array<string, mixed>
     */
    private function nest(array $leaves): array
    {
        $layout = [];

        foreach ($leaves as $path => $value) {
            $node = &$layout;

            foreach (explode('.', $path) as $key) {
                $node[$key] ??= [];
                $node = &$node[$key];
            }

            $node = $value;
        }

        return $layout;
    }

    /**
     * @return array<string, mixed>
     */
    private function createLayout(): array
    {
        $shipping = self::SHIPPING_FIELDS;
        $magePlaza = self::MAGEPLAZA_FIELDS;
        $billingForm = self::PAYMENTS_FIELDS;
        $sharedForm = self::SHARED_FIELDS;
        $payments = self::PAYMENTS_LIST;

        return $this->nest([
            "$shipping.country_id" => ['sortOrder' => '10'],
            "$shipping.street" => ['sortOrder' => '20', 'dataScope' => 'shippingAddress.street'],
            "$shipping.postcode" => ['sortOrder' => '30'],
            "$shipping.city" => ['sortOrder' => '40'],
            "$shipping.region" => ['sortOrder' => '50'],
            "$shipping.region_id" => ['sortOrder' => '60'],
            "$shipping.address_autofill_nl" => [
                'sortOrder' => '100',
                'config' => ['customScope' => 'shippingAddress'],
                'dataScope' => 'shippingAddress.address_autofill_nl',
            ],
            "$shipping.address_autofill_intl" => [
                'sortOrder' => '110',
                'config' => ['customScope' => 'shippingAddress'],
            ],
            "$shipping.address_autofill_formatted_output" => [
                'sortOrder' => '120',
                'config' => ['customScope' => 'shippingAddress'],
                'children' => [
                    'nested_field' => [
                        'config' => ['customScope' => 'shippingAddress'],
                        'dataScope' => 'shippingAddress.nested_field',
                    ],
                    'plain_field' => ['config' => ['someOption' => 'kept']],
                ],
            ],
            "$shipping.address_autofill_bypass" => [
                'sortOrder' => '130',
                'config' => ['someOption' => 'kept'],
            ],
            "$magePlaza.firstname" => [
                'dataScope' => 'shippingAddress.firstname',
                'config' => ['customScope' => 'billingAddress'],
                'children' => ['nested' => ['dataScope' => 'shippingAddress.nested']],
            ],
            "$payments.billing-address-form.dataScopePrefix" => 'billingAddress',
            "$payments.noprefix-form.children.form-fields.children.firstname" => ['dataScope' => 'shippingAddress.firstname'],
            "$billingForm.firstname" => [
                'dataScope' => 'shippingAddress.firstname',
                'children' => ['nested' => ['dataScope' => 'shippingAddress.nested']],
            ],
            "$billingForm.plain_no_scope" => ['config' => ['someOption' => 'kept']],
            "$billingForm.odd_scope" => ['dataScope' => 'foo.shippingAddress.bar'],
            "$billingForm.prefix_lookalike" => ['dataScope' => 'shippingAddressFoo.bar'],
            "$billingForm.root_scope" => ['dataScope' => 'shippingAddress'],
            "$payments.checkmo.children.form-fields.children.ignored" => ['dataScope' => 'shippingAddress.ignored'],
            "$payments.free-form.dataScopePrefix" => 'billingAddress',
            "$sharedForm.firstname" => [
                'dataScope' => 'shippingAddress.firstname',
                'children' => ['nested' => ['dataScope' => 'shippingAddress.nested']],
            ],
        ]);
    }
}
