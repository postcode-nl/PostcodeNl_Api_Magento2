<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Block\Checkout;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Helper\Address;
use Magento\Customer\Model\Session;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\AllowedCountries;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Block\Checkout\AttributeMerger;

/**
 * Street field group override for the checkout attribute merger.
 */
class AttributeMergerTest extends TestCase
{
    private const ATTRIBUTE_CONFIG = [
        'size' => 2,
        'label' => 'Street Address',
        'required' => true,
        'sortOrder' => 100,
        'validation' => [],
    ];

    #[Test]
    public function street_attribute_uses_postcode_eu_component_and_template(): void
    {
        $merger = $this->createMerger();

        $config = $merger->expose('street', self::ATTRIBUTE_CONFIG, 'shippingAddress', 'shippingAddress');

        $this->assertSame('PostcodeEu_AddressValidation/js/form/components/street/group', $config['component']);
        $this->assertSame('PostcodeEu_AddressValidation/group/street', $config['config']['template']);
    }

    #[Test]
    public function street_attribute_keeps_parent_configuration(): void
    {
        $merger = $this->createMerger();

        $config = $merger->expose('street', self::ATTRIBUTE_CONFIG, 'shippingAddress', 'shippingAddress');

        $this->assertSame('Street Address', $config['label']);
        $this->assertSame(100, $config['sortOrder']);
        $this->assertSame('shippingAddress.street', $config['dataScope']);
        $this->assertSame('shippingAddress', $config['provider']);
        $this->assertSame('street', $config['config']['additionalClasses']);
        $this->assertCount(2, $config['children']);
    }

    #[Test]
    public function other_attribute_keeps_parent_component_and_template(): void
    {
        $merger = $this->createMerger();

        $config = $merger->expose('firstname', self::ATTRIBUTE_CONFIG, 'shippingAddress', 'shippingAddress');

        $this->assertSame('Magento_Ui/js/form/components/group', $config['component']);
        $this->assertSame('ui/group/group', $config['config']['template']);
    }

    private function createMerger(): AttributeMerger
    {
        $directoryHelper = $this->createStub(DirectoryHelper::class);
        $directoryHelper->method('getTopCountryCodes')->willReturn([]);

        return new class(
            $this->createStub(Address::class),
            $this->createStub(Session::class),
            $this->createStub(CustomerRepositoryInterface::class),
            $directoryHelper,
            $this->createStub(AllowedCountries::class)
        ) extends AttributeMerger {
            /**
             * @param array<string, mixed> $attributeConfig
             * @return array<string, mixed>
             */
            public function expose(
                string $attributeCode,
                array $attributeConfig,
                string $providerName,
                string $dataScopePrefix
            ): array {
                return $this->getMultilineFieldConfig($attributeCode, $attributeConfig, $providerName, $dataScopePrefix);
            }
        };
    }
}
