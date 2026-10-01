<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Model\Config\Source;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Model\Config\Source\DisabledCountries;

/**
 * Supported-country map to option-array shaping for DisabledCountries.
 */
class DisabledCountriesTest extends TestCase
{
    #[Test]
    #[DataProvider('supportedCountriesProvider')]
    public function supported_countries_map_to_value_label_options(array $countries, array $expected): void
    {
        $storeConfigHelper = $this->createStub(StoreConfigHelper::class);
        $storeConfigHelper->method('getSupportedCountryNames')->willReturn($countries);

        $source = new DisabledCountries($storeConfigHelper);

        $this->assertSame($expected, $source->toOptionArray());
    }

    /**
     * @return array<string, array{array<string, string>, array<int, array{value: string, label: string}>}>
     */
    public static function supportedCountriesProvider(): array
    {
        return [
            'single country' => [
                ['NL' => 'Netherlands'],
                [['value' => 'NL', 'label' => 'Netherlands']],
            ],
            'multiple countries preserve order' => [
                ['DE' => 'Germany', 'BE' => 'Belgium', 'SE' => 'Sweden'],
                [
                    ['value' => 'DE', 'label' => 'Germany'],
                    ['value' => 'BE', 'label' => 'Belgium'],
                    ['value' => 'SE', 'label' => 'Sweden'],
                ],
            ],
            'empty map' => [[], []],
        ];
    }
}
