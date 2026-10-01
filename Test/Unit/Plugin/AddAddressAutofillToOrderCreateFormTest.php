<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Plugin;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Fieldset;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Sales\Block\Adminhtml\Order\Address\Form as EditForm;
use Magento\Sales\Block\Adminhtml\Order\Create\Form\Address as AddressBlock;
use Magento\Sales\Model\Order\Address;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Data\Form\Element\AddressAutofill;
use PostcodeEu\AddressValidation\Helper\Data as DataHelper;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Model\Config\Source\AdminAddressAutocompleteBehavior;
use PostcodeEu\AddressValidation\Plugin\AddAddressAutofillToOrderCreateForm;

/**
 * Address autofill field injection on the admin order create form.
 */
// Subjects are partial mocks of concrete Magento classes (getStoreId/getIsShipping/getAddress
// stubbed only), so there are no expectations to verify; a plain stub cannot partially stub a class.
#[AllowMockObjectsWithoutExpectations]
class AddAddressAutofillToOrderCreateFormTest extends TestCase
{
    use MockCreationTrait;

    private const STORE_ID = 7;
    private const HTML_ID_PREFIX = 'order_';
    private const FIELDSET_ID = 'main';
    private const FIELD_TYPE = 'postcode-eu-address-autofill';

    #[Test]
    public function missing_main_fieldset_returns_original_form_untouched(): void
    {
        $subject = $this->createEarlySubject();
        $form = $this->createForm(null);
        $dataHelper = $this->createMock(DataHelper::class);
        $dataHelper->expects($this->never())->method('isDisabled');

        $plugin = $this->createPlugin($dataHelper);

        $result = $plugin->afterGetForm($subject, $form);

        $this->assertSame($form, $result);
    }

    #[Test]
    public function disabled_module_returns_original_form(): void
    {
        $subject = $this->createEarlySubject();
        $fieldset = $this->createNeverAddingFieldset();
        $form = $this->createForm($fieldset);
        $dataHelper = $this->createMock(DataHelper::class);
        $dataHelper->expects($this->once())->method('isDisabled')->with(self::STORE_ID)->willReturn(true);

        $plugin = $this->createPlugin($dataHelper);

        $result = $plugin->afterGetForm($subject, $form);

        $this->assertSame($form, $result);
    }

    #[Test]
    public function disable_behavior_returns_original_form(): void
    {
        $subject = $this->createEarlySubject();
        $fieldset = $this->createNeverAddingFieldset();
        $form = $this->createForm($fieldset);
        $dataHelper = $this->createMock(DataHelper::class);
        $dataHelper->expects($this->once())->method('isDisabled')->with(self::STORE_ID)->willReturn(false);
        $dataHelper->expects($this->never())->method('isNlComponentDisabled');

        $plugin = $this->createPlugin($dataHelper, AdminAddressAutocompleteBehavior::DISABLE);

        $result = $plugin->afterGetForm($subject, $form);

        $this->assertSame($form, $result);
    }

    #[Test]
    public function edit_form_returns_original_form(): void
    {
        $subject = $this->createPartialMockWithReflection(EditForm::class, ['getStoreId']);
        $subject->method('getStoreId')->willReturn(self::STORE_ID);
        $fieldset = $this->createNeverAddingFieldset();
        $form = $this->createForm($fieldset);
        $dataHelper = $this->createStub(DataHelper::class);
        $dataHelper->method('isDisabled')->willReturn(false);

        $plugin = $this->createPlugin($dataHelper);

        $result = $plugin->afterGetForm($subject, $form);

        $this->assertSame($form, $result);
    }

    #[Test]
    public function shipping_address_adds_autofill_field_after_country(): void
    {
        $settings = ['enabled_countries' => ['NL', 'BE']];

        $captured = $this->exerciseHappyPath(
            true,
            'NL',
            ['NL', 'BE'],
            AdminAddressAutocompleteBehavior::DEFAULT,
            false,
            'DE',
            $settings
        );

        $this->assertSame('shipping_address_autofill', $captured['id']);
        $this->assertSame(self::FIELD_TYPE, $captured['type']);
        $this->assertSame('country_id', $captured['after']);
        $this->assertSame(
            ['settings', 'htmlIdPrefix', 'addressType', 'label', 'countryCode', 'visible', 'css_class', 'isNlComponentDisabled'],
            array_keys($captured['config'])
        );
        $this->assertSame($settings, $captured['config']['settings']);
        $this->assertSame(self::HTML_ID_PREFIX, $captured['config']['htmlIdPrefix']);
        $this->assertSame('shipping', $captured['config']['addressType']);
        $this->assertSame('Address autocomplete', $captured['config']['label']->getText());
        $this->assertSame('NL', $captured['config']['countryCode']);
        $this->assertTrue($captured['config']['visible']);
        $this->assertSame('', $captured['config']['css_class']);
        $this->assertFalse($captured['config']['isNlComponentDisabled']);
    }

    #[Test]
    public function billing_address_uses_billing_field_id(): void
    {
        $captured = $this->exerciseHappyPath(false, 'DE', ['DE']);

        $this->assertSame('billing_address_autofill', $captured['id']);
        $this->assertSame('billing', $captured['config']['addressType']);
        $this->assertSame('DE', $captured['config']['countryCode']);
    }

    #[Test]
    public function missing_country_falls_back_to_default_country(): void
    {
        $captured = $this->exerciseHappyPath(
            true,
            null,
            ['BE'],
            AdminAddressAutocompleteBehavior::DEFAULT,
            false,
            'BE'
        );

        $this->assertSame('BE', $captured['config']['countryCode']);
        $this->assertTrue($captured['config']['visible']);
    }

    #[Test]
    #[DataProvider('countryVisibilityProvider')]
    public function country_visibility_drives_field_config(
        string $countryId,
        array $enabledCountries,
        bool $visible,
        string $cssClass
    ): void {
        $captured = $this->exerciseHappyPath(true, $countryId, $enabledCountries);

        $this->assertSame($visible, $captured['config']['visible']);
        $this->assertSame($cssClass, $captured['config']['css_class']);
    }

    /**
     * @return array<string, array{string, array<int, string>, bool, string}>
     */
    public static function countryVisibilityProvider(): array
    {
        return [
            'enabled country' => ['NL', ['NL', 'BE'], true, ''],
            'country not enabled' => ['FR', ['NL', 'BE'], false, 'hidden'],
        ];
    }

    #[Test]
    #[DataProvider('nlComponentProvider')]
    public function nl_component_flag_drives_field_config(
        string $behavior,
        bool $helperValue,
        bool $expected
    ): void {
        $captured = $this->exerciseHappyPath(true, 'NL', ['NL'], $behavior, $helperValue);

        $this->assertSame($expected, $captured['config']['isNlComponentDisabled']);
    }

    /**
     * @return array<string, array{string, bool, bool}>
     */
    public static function nlComponentProvider(): array
    {
        return [
            'default forwards helper disabled' => [AdminAddressAutocompleteBehavior::DEFAULT, false, false],
            'default forwards helper enabled' => [AdminAddressAutocompleteBehavior::DEFAULT, true, true],
            'single input forces disabled' => [AdminAddressAutocompleteBehavior::SINGLE_INPUT, false, true],
            'dutch lookup leaves nl component enabled' => [AdminAddressAutocompleteBehavior::DUTCH_LOOKUP, true, false],
            'unknown behavior falls back to helper enabled' => ['unexpected', true, true],
            'unknown behavior falls back to helper disabled' => ['unexpected', false, false],
        ];
    }

    #[Test]
    public function existing_autofill_field_skips_add_field(): void
    {
        $subject = $this->createFullSubject(true, 'NL');
        $fieldset = $this->createMock(Fieldset::class);
        $fieldset->expects($this->once())
            ->method('addType')
            ->with(self::FIELD_TYPE, AddressAutofill::class);
        $fieldset->expects($this->never())->method('addField');
        $form = $this->createForm($fieldset, $this->createStub(AbstractElement::class));
        $dataHelper = $this->createStub(DataHelper::class);
        $dataHelper->method('isDisabled')->willReturn(false);

        $plugin = $this->createPlugin($dataHelper, AdminAddressAutocompleteBehavior::DEFAULT, ['NL']);

        $result = $plugin->afterGetForm($subject, $form);

        $this->assertSame($form, $result);
    }

    /**
     * @return array{id: string, type: string, config: array<string, mixed>, after: string}
     */
    private function exerciseHappyPath(
        bool $isShipping,
        ?string $countryId,
        array $enabledCountries,
        string $behavior = AdminAddressAutocompleteBehavior::DEFAULT,
        bool $nlComponentDisabled = false,
        string $defaultCountry = 'NL',
        array $settings = []
    ): array {
        $subject = $this->createFullSubject($isShipping, $countryId);
        $fieldset = $this->createMock(Fieldset::class);
        $fieldset->expects($this->once())
            ->method('addType')
            ->with(self::FIELD_TYPE, AddressAutofill::class);
        $captured = [];
        $fieldset->expects($this->once())
            ->method('addField')
            ->willReturnCallback(
                static function (string $id, string $type, array $config, $after) use (&$captured): void {
                    $captured = ['id' => $id, 'type' => $type, 'config' => $config, 'after' => $after];
                }
            );
        $form = $this->createForm($fieldset);
        $dataHelper = $this->createStub(DataHelper::class);
        $dataHelper->method('isDisabled')->willReturn(false);
        $dataHelper->method('isNlComponentDisabled')->willReturn($nlComponentDisabled);

        $plugin = $this->createPlugin($dataHelper, $behavior, $enabledCountries, $settings, $defaultCountry);

        $result = $plugin->afterGetForm($subject, $form);

        $this->assertSame($form, $result);

        return $captured;
    }

    private function createEarlySubject(): AddressBlock
    {
        $subject = $this->createPartialMockWithReflection(AddressBlock::class, ['getStoreId']);
        $subject->method('getStoreId')->willReturn(self::STORE_ID);

        return $subject;
    }

    private function createFullSubject(bool $isShipping, ?string $countryId): AddressBlock
    {
        $address = $this->createStub(Address::class);
        $address->method('getCountryId')->willReturn($countryId);

        $subject = $this->createPartialMockWithReflection(
            AddressBlock::class,
            ['getStoreId', 'getIsShipping', 'getAddress']
        );
        $subject->method('getStoreId')->willReturn(self::STORE_ID);
        $subject->method('getIsShipping')->willReturn($isShipping);
        $subject->method('getAddress')->willReturn($address);

        return $subject;
    }

    private function createNeverAddingFieldset(): Fieldset
    {
        $fieldset = $this->createMock(Fieldset::class);
        $fieldset->expects($this->never())->method('addType');
        $fieldset->expects($this->never())->method('addField');

        return $fieldset;
    }

    private function createForm(?Fieldset $fieldset, ?AbstractElement $existingElement = null): Form
    {
        $form = $this->createPartialMockWithReflection(Form::class, ['getElement', 'getHtmlIdPrefix']);
        $form->expects($this->atLeastOnce())
            ->method('getElement')
            ->willReturnCallback(
                static fn (string $elementId): ?AbstractElement => $elementId === self::FIELDSET_ID
                    ? $fieldset
                    : $existingElement
            );
        $form->method('getHtmlIdPrefix')->willReturn(self::HTML_ID_PREFIX);

        return $form;
    }

    private function createPlugin(
        DataHelper $dataHelper,
        string $behavior = AdminAddressAutocompleteBehavior::DEFAULT,
        array $enabledCountries = ['NL', 'BE'],
        array $settings = [],
        string $defaultCountry = 'NL'
    ): AddAddressAutofillToOrderCreateForm {
        $storeConfigHelper = $this->createMock(StoreConfigHelper::class);
        $storeConfigHelper->expects($this->once())
            ->method('getValue')
            ->with('admin_address_autocomplete_behavior', self::STORE_ID)
            ->willReturn($behavior);
        $storeConfigHelper->method('getEnabledCountries')->willReturnCallback(
            fn (int $storeId) => $storeId === self::STORE_ID ? $enabledCountries : []
        );
        $storeConfigHelper->method('getJsinit')->willReturnCallback(
            fn (int $storeId) => $storeId === self::STORE_ID ? $settings : []
        );

        $directoryHelper = $this->createStub(DirectoryHelper::class);
        $directoryHelper->method('getDefaultCountry')->willReturn($defaultCountry);

        return new AddAddressAutofillToOrderCreateForm($storeConfigHelper, $dataHelper, $directoryHelper);
    }
}
