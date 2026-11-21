<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Exception as WebapiException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Api\Data\Autocomplete as AutocompleteData;
use PostcodeEu\AddressValidation\Helper\ApiClientHelper;
use PostcodeEu\AddressValidation\Model\PostcodeModel;
use PostcodeEu\AddressValidation\Service\CsrfValidator;

/**
 * Request-entry contract for PostcodeModel: CSRF gate and response wrapping.
 */
class PostcodeModelTest extends TestCase
{
    private const ADDRESS_CONTEXT = 'ctx-1';

    #[Test]
    #[DataProvider('requestEntryProvider')]
    public function csrf_validation_runs_before_delegation(
        string $method,
        array $args,
        string $helperMethod
    ): void {
        $csrfValidator = $this->createStub(CsrfValidator::class);
        $csrfValidator->method('validate')
            ->willThrowException(new LocalizedException(new Phrase('Invalid request')));

        $helper = $this->createMock(ApiClientHelper::class);
        $helper->expects($this->never())->method($helperMethod);

        $model = new PostcodeModel($helper, $csrfValidator);

        $this->expectException(WebapiException::class);

        $model->$method(...$args);
    }

    /**
     * @return array<string, array{string, array, string}>
     */
    public static function requestEntryProvider(): array
    {
        return [
            'autocomplete' => ['getAddressAutocomplete', ['nld', 'Damrak'], 'getAddressAutocomplete'],
            'details' => ['getAddressDetails', [self::ADDRESS_CONTEXT], 'getAddressDetails'],
            'nl address' => ['getNlAddress', ['2012ES', '30'], 'getNlAddress'],
            'validate address' => [
                'validateAddress',
                ['NL', '1011 AB', 'Amsterdam', 'Damrak', '1'],
                'validateAddress',
            ],
        ];
    }

    #[Test]
    public function csrf_failure_raises_forbidden_webapi_error(): void
    {
        $csrfValidator = $this->createStub(CsrfValidator::class);
        $csrfValidator->method('validate')
            ->willThrowException(new LocalizedException(new Phrase('Invalid request')));

        $helper = $this->createMock(ApiClientHelper::class);
        $helper->expects($this->never())->method('getNlAddress');

        $model = new PostcodeModel($helper, $csrfValidator);

        try {
            $model->getNlAddress('1234AB', '5');
        } catch (WebapiException $e) {
            $this->assertSame('Invalid request', $e->getMessage());
            $this->assertSame(WebapiException::HTTP_FORBIDDEN, $e->getHttpCode());
            return;
        }

        $this->fail('Expected a webapi exception.');
    }

    #[Test]
    public function autocomplete_result_is_shaped_into_autocomplete_data(): void
    {
        $helper = $this->createMock(ApiClientHelper::class);
        $helper->expects($this->once())
            ->method('getAddressAutocomplete')
            ->with('nld', 'Damrak')
            ->willReturn([
                'matches' => [],
                'error' => 'No matching addresses found.',
                'message' => 'Try another search.',
            ]);

        $result = $this->createModel($helper)->getAddressAutocomplete('nld', 'Damrak');

        $this->assertInstanceOf(AutocompleteData::class, $result);
        $this->assertSame('No matching addresses found.', $result->getError());
        $this->assertSame('Try another search.', $result->getMessage());
    }

    #[Test]
    #[DataProvider('wrappingProvider')]
    public function helper_payload_is_wrapped_in_single_element_array(
        string $method,
        array $args,
        string $helperMethod,
        array $payload
    ): void {
        $helper = $this->createStub(ApiClientHelper::class);
        $helper->method($helperMethod)->willReturn($payload);

        $result = $this->createModel($helper)->$method(...$args);

        $this->assertSame([$payload], $result);
    }

    /**
     * @return array<string, array{string, array, string, array}>
     */
    public static function wrappingProvider(): array
    {
        return [
            'details' => [
                'getAddressDetails',
                [self::ADDRESS_CONTEXT],
                'getAddressDetails',
                self::dutchAddressDetails(),
            ],
            'nl address' => [
                'getNlAddress',
                ['2012ES', '30'],
                'getNlAddress',
                [
                    'address' => [
                        'street' => 'Julianastraat',
                        'streetNen' => 'Julianastraat',
                        'houseNumber' => 30,
                        'houseNumberAddition' => '',
                        'postcode' => '2012ES',
                        'city' => 'Haarlem',
                        'cityShort' => 'Haarlem',
                        'cityId' => '2907',
                        'municipality' => 'Haarlem',
                        'municipalityShort' => 'Haarlem',
                        'municipalityId' => '0392',
                        'province' => 'Noord-Holland',
                        'rdX' => 103242,
                        'rdY' => 487716,
                        'latitude' => 52.37487801,
                        'longitude' => 4.62714526,
                        'bagNumberDesignationId' => '0392200000029398',
                        'bagAddressableObjectId' => '0392010000029398',
                        'addressType' => 'building',
                        'purposes' => ['office'],
                        'surfaceArea' => 643,
                        'houseNumberAdditions' => [
                            ['label' => '30', 'value' => '30', 'houseNumberAddition' => ''],
                        ],
                    ],
                    'status' => 'valid',
                ],
            ],
        ];
    }

    #[Test]
    #[DataProvider('validateAddressArgProvider')]
    public function validate_forwards_every_supplied_argument(array $args): void
    {
        $forwarded = null;

        $helper = $this->createMock(ApiClientHelper::class);
        $helper->expects($this->once())
            ->method('validateAddress')
            ->willReturnCallback(function (...$received) use (&$forwarded): array {
                $forwarded = $received;
                return ['matches' => []];
            });

        $result = $this->createModel($helper)->validateAddress(...$args);

        $this->assertSame($args, $forwarded);
        $this->assertSame([['matches' => []]], $result);
    }

    /**
     * @return array<string, array{array}>
     */
    public static function validateAddressArgProvider(): array
    {
        return [
            'country only' => [['NL']],
            'all seven arguments' => [['NL', '1011AB', 'Amsterdam', 'Damrak', '1', 'Noord-Holland', 'Damrak 1']],
        ];
    }

    /**
     * @return array
     */
    private static function dutchAddressDetails(): array
    {
        return [
            'language' => 'nl-NL',
            'address' => [
                'country' => 'Netherlands',
                'locality' => 'Amsterdam',
                'street' => 'Damrak',
                'postcode' => '1011 AB',
                'building' => '1',
                'buildingNumber' => 1,
                'buildingNumberAddition' => null,
            ],
            'mailLines' => ['Damrak 1', '1011 AB Amsterdam'],
            'location' => [
                'longitude' => 4.893604,
                'latitude' => 52.377956,
                'precision' => 'Address',
            ],
            'isPoBox' => false,
            'country' => [
                'name' => 'Netherlands',
                'iso3Code' => 'NLD',
                'iso2Code' => 'NL',
            ],
            'details' => [
                'nldProvince' => ['name' => 'Noord-Holland'],
            ],
            'region' => ['id' => 11, 'name' => 'Noord-Holland'],
            'streetLines' => ['Damrak 1'],
        ];
    }

    /**
     * @param ApiClientHelper $helper
     * @return PostcodeModel
     */
    private function createModel(ApiClientHelper $helper): PostcodeModel
    {
        return new PostcodeModel($helper, $this->createStub(CsrfValidator::class));
    }
}
