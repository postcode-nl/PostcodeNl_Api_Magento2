<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Helper;

use Magento\Customer\Helper\Address as AddressHelper;
use Magento\Developer\Helper\Data as DeveloperHelper;
use Magento\Directory\Model\Region;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Helper\ApiClientHelper;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Service\ApiAvailabilityMonitor;
use PostcodeEu\AddressValidation\Service\Exception\AuthenticationException;
use PostcodeEu\AddressValidation\Service\Exception\BadRequestException;
use PostcodeEu\AddressValidation\Service\Exception\CurlException;
use PostcodeEu\AddressValidation\Service\Exception\ForbiddenException;
use PostcodeEu\AddressValidation\Service\Exception\NotFoundException;
use PostcodeEu\AddressValidation\Service\Exception\ServiceUnavailableException;
use PostcodeEu\AddressValidation\Service\Exception\UnexpectedException;
use PostcodeEu\AddressValidation\Service\PostcodeApiClient;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Country-code mapping, Dutch lookups, response shaping and orchestration for ApiClientHelper.
 */
class ApiClientHelperTest extends TestCase
{
    #[Test]
    public function supported_iso2_codes_map_to_iso3(): void
    {
        $helper = $this->createHelper([
            (object)['iso2' => 'NL', 'iso3' => 'nld'],
            (object)['iso2' => 'DE', 'iso3' => 'deu'],
        ]);

        $this->assertSame('nld', $helper->getCountryIso3Code('NL'));
        $this->assertSame('nld', $helper->getCountryIso3Code('nl'));
        $this->assertSame('deu', $helper->getCountryIso3Code('DE'));
    }

    #[Test]
    public function unknown_iso2_codes_are_not_mapped(): void
    {
        $helper = $this->createHelper([
            (object)['iso2' => 'NL', 'iso3' => 'nld'],
        ]);

        $this->assertNull($helper->getCountryIso3Code('XX'));
    }

    #[Test]
    public function autocomplete_maps_iso2_context_to_iso3_before_lookup(): void
    {
        $client = $this->createMock(PostcodeApiClient::class);
        $client->expects($this->once())
            ->method('internationalAutocomplete')
            ->with('deu', 'Damrak', null, 'en')
            ->willReturn([]);

        $availabilityMonitor = $this->createStub(ApiAvailabilityMonitor::class);
        $availabilityMonitor->method('isAvailable')->willReturn(true);

        $localeResolver = $this->createStub(LocaleResolver::class);
        $localeResolver->method('getLocale')->willReturn('en_US');

        $helper = $this->createHelper(
            [(object)['iso2' => 'DE', 'iso3' => 'deu']],
            [
                'client' => $client,
                'availabilityMonitor' => $availabilityMonitor,
                'localeResolver' => $localeResolver,
            ]
        );

        $this->assertSame([], $helper->getAddressAutocomplete('DE', 'Damrak'));
    }

    #[Test]
    public function invalid_postcode_is_rejected(): void
    {
        $helper = $this->createHelper([]);

        $result = $helper->getNlAddress('0123AB', '1');

        $this->assertTrue($result['error']);
        $this->assertSame('Invalid zip code.', (string)$result['message']);
    }

    #[Test]
    public function house_number_without_leading_digits_is_rejected(): void
    {
        $helper = $this->createHelper([]);

        $result = $helper->getNlAddress('1234AB', 'AB');

        $this->assertTrue($result['error']);
        $this->assertSame('Invalid house number.', (string)$result['message']);
    }

    #[Test]
    public function dutch_lookup_forwards_postcode_and_parsed_house_number(): void
    {
        $client = $this->createMock(PostcodeApiClient::class);
        $client->expects($this->once())
            ->method('dutchAddressByPostcode')
            ->with('1234AB', 42, 'A')
            ->willReturn([
                'houseNumber' => 42,
                'houseNumberAddition' => 'A',
                'houseNumberAdditions' => ['A'],
                'street' => 'Damrak',
                'building' => '42',
            ]);

        $helper = $this->createAvailableHelper([], ['client' => $client]);

        $result = $helper->getNlAddress('1234AB', '42A');

        $this->assertSame('valid', $result['status']);
    }

    #[Test]
    public function postcode_and_house_number_whitespace_is_trimmed(): void
    {
        $client = $this->createMock(PostcodeApiClient::class);
        $client->expects($this->once())
            ->method('dutchAddressByPostcode')
            ->with('1234AB', 42, 'A')
            ->willReturn([
                'houseNumber' => 42,
                'houseNumberAddition' => 'A',
                'houseNumberAdditions' => ['A'],
                'street' => 'Damrak',
                'building' => '42',
            ]);

        $helper = $this->createAvailableHelper([], ['client' => $client]);

        $result = $helper->getNlAddress(' 1234AB ', ' 42A ');

        $this->assertSame('valid', $result['status']);
    }

    #[Test]
    public function valid_lookup_returns_formatted_addition_options(): void
    {
        $address = [
            'houseNumber' => 42,
            'houseNumberAddition' => 'A',
            'houseNumberAdditions' => ['A', 'B'],
            'street' => 'Damrak',
            'building' => '42',
        ];

        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('dutchAddressByPostcode')->willReturn($address);

        $helper = $this->createAvailableHelper([], ['client' => $client]);

        $result = $helper->getNlAddress('1234AB', '42A');

        $this->assertSame('valid', $result['status']);
        $this->assertSame('Damrak', $result['address']['street']);
        $this->assertSame(
            [
                ['label' => '42 A', 'value' => '42 A', 'houseNumberAddition' => 'A'],
                ['label' => '42 B', 'value' => '42 B', 'houseNumberAddition' => 'B'],
            ],
            $result['address']['houseNumberAdditions']
        );
    }

    #[Test]
    public function addition_mismatch_appends_unknown_addition_option(): void
    {
        $address = [
            'houseNumber' => 42,
            'houseNumberAddition' => 'A',
            'houseNumberAdditions' => ['A', 'B'],
            'street' => 'Damrak',
            'building' => '42',
        ];

        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('dutchAddressByPostcode')->willReturn($address);

        $helper = $this->createAvailableHelper([], ['client' => $client]);

        $result = $helper->getNlAddress('1234AB', '42C');

        $this->assertSame('houseNumberAdditionIncorrect', $result['status']);
        $this->assertSame(
            ['label' => '42 C (unknown addition)', 'value' => '42 C', 'houseNumberAddition' => 'C'],
            $result['address']['houseNumberAdditions'][2]
        );
    }

    #[Test]
    public function additions_present_without_requested_addition_marks_incorrect_without_unknown_option(): void
    {
        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('dutchAddressByPostcode')->willReturn([
            'houseNumber' => 42,
            'houseNumberAddition' => null,
            'houseNumberAdditions' => ['A', 'B'],
            'street' => 'Damrak',
            'building' => '42',
        ]);

        $helper = $this->createAvailableHelper([], ['client' => $client]);

        $result = $helper->getNlAddress('1234AB', '42');

        $this->assertSame('houseNumberAdditionIncorrect', $result['status']);
        $this->assertSame(
            [
                ['label' => '42 A', 'value' => '42 A', 'houseNumberAddition' => 'A'],
                ['label' => '42 B', 'value' => '42 B', 'houseNumberAddition' => 'B'],
            ],
            $result['address']['houseNumberAdditions']
        );
    }

    #[Test]
    public function missing_address_is_reported_as_not_found(): void
    {
        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('dutchAddressByPostcode')
            ->willThrowException(new NotFoundException('Combination not found.'));

        $helper = $this->createAvailableHelper([], ['client' => $client]);

        $result = $helper->getNlAddress('1234AB', '42');

        $this->assertSame(['status' => 'notFound', 'address' => null], $result);
    }

    #[Test]
    public function debug_enabled_on_dutch_lookup_adds_parsed_input_and_debug_info(): void
    {
        $address = [
            'houseNumber' => 42,
            'houseNumberAddition' => 'A',
            'houseNumberAdditions' => ['A'],
            'street' => 'Damrak',
            'building' => '42',
        ];

        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('dutchAddressByPostcode')->willReturn($address);
        $client->method('getUserAgent')->willReturn('test-agent');

        $request = $this->createStub(Request::class);
        $request->method('getHeader')->willReturn('session-id');

        $helper = $this->createAvailableHelper([], [
            'client' => $client,
            'request' => $request,
            'storeConfigHelper' => $this->createStoreConfigHelper(true),
        ]);

        $result = $helper->getNlAddress('1234AB', '42A');

        $this->assertSame(
            ['parsedHouseNumber' => 42, 'parsedHouseNumberAddition' => 'A'],
            $result['address']['debug']
        );
        $this->assertArrayHasKey('magento_debug_info', $result);
        $this->assertSame('session-id', $result['magento_debug_info']['session']);
    }

    #[Test]
    public function details_lookup_is_enriched_with_region_and_street_lines(): void
    {
        $response = [
            'country' => ['iso2Code' => 'NL'],
            'address' => ['street' => 'Damrak', 'building' => '1'],
            'details' => ['nldProvince' => ['name' => 'North Holland']],
        ];

        $client = $this->createMock(PostcodeApiClient::class);
        $client->expects($this->once())
            ->method('internationalGetDetails')
            ->with('NL-1234', 'session-id')
            ->willReturn($response);

        $request = $this->createStub(Request::class);
        $request->method('getHeader')->willReturn('session-id');

        $addressHelper = $this->createStub(AddressHelper::class);
        $addressHelper->method('getStreetLines')->willReturn(1);

        $helper = $this->createAvailableHelper([], [
            'client' => $client,
            'request' => $request,
            'addressHelper' => $addressHelper,
            'regionFactory' => $this->createRegionFactory([
                'NL:name:North Holland' => ['found' => true, 'id' => 11, 'name' => 'North Holland'],
            ]),
        ]);

        $result = $helper->getAddressDetails('NL-1234');

        $this->assertSame(['id' => 11, 'name' => 'North Holland'], $result['region']);
        $this->assertSame(['Damrak 1'], $result['streetLines']);
    }

    #[Test]
    public function cache_control_header_is_repeated_on_response(): void
    {
        $response = [
            'country' => ['iso2Code' => 'NL'],
            'address' => ['street' => 'Damrak', 'building' => '1'],
            'details' => ['nldProvince' => ['name' => 'North Holland']],
        ];

        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('internationalGetDetails')->willReturn($response);
        $client->method('getMostRecentResponseHeaders')->willReturn(['cache-control' => 'max-age=60']);

        $addressHelper = $this->createStub(AddressHelper::class);
        $addressHelper->method('getStreetLines')->willReturn(1);

        $responseMock = $this->createMock(Response::class);
        $responseMock->expects($this->once())
            ->method('setHeader')
            ->with('cache-control', 'max-age=60');

        $helper = $this->createAvailableHelper([], [
            'client' => $client,
            'response' => $responseMock,
            'addressHelper' => $addressHelper,
            'regionFactory' => $this->createRegionFactory([
                'NL:name:North Holland' => ['found' => true, 'id' => 11, 'name' => 'North Holland'],
            ]),
        ]);

        $helper->getAddressDetails('NL-1234');
    }

    #[Test]
    #[DataProvider('streetLineProvider')]
    public function street_lines_are_formatted_per_country_and_split_config(
        bool $split,
        int $streetLines,
        string $country,
        array $address,
        array $details,
        array $expected
    ): void {
        $response = [
            'country' => ['iso2Code' => $country],
            'address' => $address,
            'details' => $details,
        ];

        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('internationalGetDetails')->willReturn($response);

        $addressHelper = $this->createStub(AddressHelper::class);
        $addressHelper->method('getStreetLines')->willReturn($streetLines);

        $helper = $this->createAvailableHelper([], [
            'client' => $client,
            'addressHelper' => $addressHelper,
            'storeConfigHelper' => $this->createStoreConfigHelper(false, $split),
        ]);

        $result = $helper->getAddressDetails('ctx');

        $this->assertSame($expected, $result['streetLines']);
    }

    /**
     * @return array<string, array{bool, int, string, array<string, mixed>, array<string, mixed>, array<int, string>}>
     */
    public static function streetLineProvider(): array
    {
        $fixedLayoutAddress = [
            'street' => 'Grand Rue',
            'building' => '12',
            'buildingNumber' => '',
            'buildingNumberAddition' => '',
        ];

        return [
            'split street values joins remainder' => [
                true,
                2,
                'AT',
                [
                    'street' => 'Damrak',
                    'building' => '42A',
                    'buildingNumber' => '42',
                    'buildingNumberAddition' => 'A',
                ],
                [],
                ['Damrak', '42 A'],
            ],
            'luxembourg fixed layout' => [
                false,
                1,
                'LU',
                $fixedLayoutAddress,
                ['luxCanton' => ['name' => 'Luxembourg']],
                ['12, Grand Rue'],
            ],
            'france fixed layout' => [
                false,
                1,
                'FR',
                $fixedLayoutAddress,
                ['fraDepartment' => ['name' => 'Paris']],
                ['12 Grand Rue'],
            ],
            'united kingdom empty street' => [
                false,
                1,
                'GB',
                [
                    'street' => '',
                    'building' => '10 Downing Street',
                    'buildingNumber' => '',
                    'buildingNumberAddition' => '',
                ],
                ['gbrBuilding' => ['number' => null, 'addition' => null]],
                ['10 Downing Street'],
            ],
            'united kingdom no building number' => [
                false,
                2,
                'GB',
                [
                    'street' => 'Downing Street',
                    'building' => '10',
                    'buildingNumber' => '',
                    'buildingNumberAddition' => '',
                ],
                ['gbrBuilding' => ['number' => null, 'addition' => null]],
                ['10', 'Downing Street'],
            ],
            'united kingdom with building number' => [
                false,
                1,
                'GB',
                [
                    'street' => 'Downing Street',
                    'building' => '10',
                    'buildingNumber' => '',
                    'buildingNumberAddition' => '',
                ],
                ['gbrBuilding' => ['number' => 10, 'addition' => null]],
                ['10 Downing Street'],
            ],
            'united kingdom multi line splits on comma' => [
                false,
                2,
                'GB',
                [
                    'street' => 'High Street, London',
                    'building' => 'Flat 1',
                    'buildingNumber' => '',
                    'buildingNumberAddition' => '',
                ],
                ['gbrBuilding' => ['number' => 10, 'addition' => 'A']],
                ['Flat 1 High Street', 'London'],
            ],
            'default layout is trimmed' => [
                false,
                1,
                'SE',
                [
                    'street' => 'Hauptstraße',
                    'building' => '',
                    'buildingNumber' => '',
                    'buildingNumberAddition' => '',
                ],
                [],
                ['Hauptstraße'],
            ],
        ];
    }

    #[Test]
    #[DataProvider('regionProvider')]
    public function region_is_resolved_per_country(
        string $country,
        array $details,
        array $lookups,
        array $expected
    ): void {
        $response = [
            'country' => ['iso2Code' => $country],
            'address' => ['street' => 'Main Street', 'building' => '1'],
            'details' => $details,
        ];

        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('internationalGetDetails')->willReturn($response);

        $addressHelper = $this->createStub(AddressHelper::class);
        $addressHelper->method('getStreetLines')->willReturn(1);

        $helper = $this->createAvailableHelper([], [
            'client' => $client,
            'addressHelper' => $addressHelper,
            'regionFactory' => $this->createRegionFactory($lookups),
        ]);

        $result = $helper->getAddressDetails('ctx');

        $this->assertSame($expected, $result['region']);
    }

    #[Test]
    public function unresolved_region_keeps_api_name_without_id(): void
    {
        $response = [
            'country' => ['iso2Code' => 'NL'],
            'address' => ['street' => 'Main Street', 'building' => '1'],
            'details' => ['nldProvince' => ['name' => 'Unknown Province']],
        ];

        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('internationalGetDetails')->willReturn($response);

        $addressHelper = $this->createStub(AddressHelper::class);
        $addressHelper->method('getStreetLines')->willReturn(1);

        $helper = $this->createAvailableHelper([], [
            'client' => $client,
            'addressHelper' => $addressHelper,
            'regionFactory' => $this->createRegionFactory([
                'NL:name:Unknown Province' => ['found' => false],
            ]),
        ]);

        $result = $helper->getAddressDetails('ctx');

        $this->assertSame(['id' => null, 'name' => 'Unknown Province'], $result['region']);
    }

    /**
     * @return array<string, array{string, array<string, mixed>, array<string, array<string, mixed>>, array{id: int|null, name: string|null}}>
     */
    public static function regionProvider(): array
    {
        return [
            'netherlands province' => [
                'NL',
                ['nldProvince' => ['name' => 'North Holland']],
                ['NL:name:North Holland' => ['found' => true, 'id' => 11, 'name' => 'North Holland']],
                ['id' => 11, 'name' => 'North Holland'],
            ],
            'belgium province' => [
                'BE',
                ['belProvince' => ['primaryName' => 'Antwerp']],
                ['BE:name:Antwerp' => ['found' => true, 'id' => 21, 'name' => 'Antwerp']],
                ['id' => 21, 'name' => 'Antwerp'],
            ],
            'belgium region fallback' => [
                'BE',
                ['belRegion' => ['primaryName' => 'Brussels']],
                ['BE:name:Brussels' => ['found' => true, 'id' => 22, 'name' => 'Brussels']],
                ['id' => 22, 'name' => 'Brussels'],
            ],
            'germany federal state' => [
                'DE',
                ['deuFederalState' => ['name' => 'Bavaria']],
                ['DE:name:Bavaria' => ['found' => true, 'id' => 31, 'name' => 'Bavaria']],
                ['id' => 31, 'name' => 'Bavaria'],
            ],
            'luxembourg canton' => [
                'LU',
                ['luxCanton' => ['name' => 'Luxembourg']],
                ['LU:name:Luxembourg' => ['found' => true, 'id' => 41, 'name' => 'Luxembourg']],
                ['id' => 41, 'name' => 'Luxembourg'],
            ],
            'spain slash separated alternatives' => [
                'ES',
                ['espProvince' => ['name' => 'Álava/Araba']],
                [
                    'ES:name:Álava' => ['found' => false],
                    'ES:name:Araba' => ['found' => true, 'id' => 51, 'name' => 'Araba/Álava'],
                ],
                ['id' => 51, 'name' => 'Araba/Álava'],
            ],
            'switzerland canton code' => [
                'CH',
                ['cheCanton' => ['code' => 'ZH']],
                ['CH:code:ZH' => ['found' => true, 'id' => 61, 'name' => 'Zurich']],
                ['id' => 61, 'name' => 'Zurich'],
            ],
            'italy territory code' => [
                'IT',
                ['itaTerritory' => ['code' => 'RM']],
                ['IT:code:RM' => ['found' => true, 'id' => 71, 'name' => 'Rome']],
                ['id' => 71, 'name' => 'Rome'],
            ],
            'finland region' => [
                'FI',
                ['finRegion' => ['name' => 'Uusimaa']],
                ['FI:name:Uusimaa' => ['found' => true, 'id' => 81, 'name' => 'Uusimaa']],
                ['id' => 81, 'name' => 'Uusimaa'],
            ],
            'france department' => [
                'FR',
                ['fraDepartment' => ['name' => 'Paris']],
                ['FR:name:Paris' => ['found' => true, 'id' => 91, 'name' => 'Paris']],
                ['id' => 91, 'name' => 'Paris'],
            ],
            'switzerland canton code not found' => [
                'CH', ['cheCanton' => ['code' => 'XX']], [], ['id' => null, 'name' => null],
            ],
            'italy territory code not found' => [
                'IT', ['itaTerritory' => ['code' => 'XX']], [], ['id' => null, 'name' => null],
            ],
            'austria has no region mapping' => ['AT', [], [], ['id' => null, 'name' => null]],
            'denmark has no region mapping' => ['DK', [], [], ['id' => null, 'name' => null]],
            'norway has no region mapping' => ['NO', [], [], ['id' => null, 'name' => null]],
            'sweden has no region mapping' => ['SE', [], [], ['id' => null, 'name' => null]],
            'wrong country does not resolve region' => [
                'NL',
                ['nldProvince' => ['name' => 'Bavaria']],
                ['BE:name:Bavaria' => ['found' => true, 'id' => 31, 'name' => 'Bavaria']],
                ['id' => null, 'name' => 'Bavaria'],
            ],
        ];
    }

    #[Test]
    public function not_found_sets_404_and_surfaces_message_without_debug(): void
    {
        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('internationalGetDetails')
            ->willThrowException(new NotFoundException('Combination not found.'));

        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('setHttpResponseCode')->with(404);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $helper = $this->createAvailableHelper([], [
            'client' => $client,
            'response' => $response,
            'logger' => $logger,
        ]);

        $result = $helper->getAddressDetails('ctx');

        $this->assertSame('Combination not found.', (string)$result['message']);
        $this->assertArrayNotHasKey('exception', $result);
        $this->assertArrayNotHasKey('magento_debug_info', $result);
    }

    #[Test]
    public function unavailable_monitor_sets_503(): void
    {
        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('setHttpResponseCode')->with(503);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $availabilityMonitor = $this->createStub(ApiAvailabilityMonitor::class);
        $availabilityMonitor->method('isAvailable')->willReturn(false);

        $helper = $this->createHelper([], [
            'response' => $response,
            'logger' => $logger,
            'availabilityMonitor' => $availabilityMonitor,
        ]);

        $result = $helper->getAddressDetails('ctx');

        $this->assertTrue($result['error']);
        $this->assertSame('Something went wrong. Please try again.', (string)$result['message']);
        $this->assertArrayNotHasKey('exception', $result);
        $this->assertArrayNotHasKey('magento_debug_info', $result);
    }

    #[Test]
    #[DataProvider('handledExceptionProvider')]
    public function handled_client_failure_maps_to_http_status(string $exceptionClass, int $expectedCode): void
    {
        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('internationalGetDetails')
            ->willThrowException(new $exceptionClass('boom'));

        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('setHttpResponseCode')->with($expectedCode);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('boom', $this->arrayHasKey('exception'));

        $helper = $this->createAvailableHelper([], [
            'client' => $client,
            'response' => $response,
            'logger' => $logger,
        ]);

        $result = $helper->getAddressDetails('ctx');

        $this->assertTrue($result['error']);
        $this->assertSame('Something went wrong. Please try again.', (string)$result['message']);
        $this->assertArrayNotHasKey('exception', $result);
        $this->assertArrayNotHasKey('magento_debug_info', $result);
    }

    /**
     * @return array<string, array{class-string<\Exception>, int}>
     */
    public static function handledExceptionProvider(): array
    {
        return [
            'unexpected exception' => [UnexpectedException::class, 500],
            'service unavailable exception' => [ServiceUnavailableException::class, 500],
            'bad request exception' => [BadRequestException::class, 400],
            'authentication exception' => [AuthenticationException::class, 400],
            'forbidden exception' => [ForbiddenException::class, 400],
            'curl exception' => [CurlException::class, 400],
            'generic exception' => [RuntimeException::class, 400],
        ];
    }

    #[Test]
    public function debug_enabled_on_failure_adds_exception_payload_and_debug_info(): void
    {
        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('internationalAutocomplete')
            ->willThrowException(new BadRequestException('bad input'));
        $client->method('getUserAgent')->willReturn('test-agent');

        $request = $this->createStub(Request::class);
        $request->method('getHeader')->willReturn('session-id');

        $localeResolver = $this->createStub(LocaleResolver::class);
        $localeResolver->method('getLocale')->willReturn('en_US');

        $helper = $this->createAvailableHelper([], [
            'client' => $client,
            'request' => $request,
            'localeResolver' => $localeResolver,
            'storeConfigHelper' => $this->createStoreConfigHelper(true),
        ]);

        $result = $helper->getAddressAutocomplete('nld', 'Damrak');

        $this->assertArrayHasKey('exception', $result);
        $this->assertStringContainsString(BadRequestException::class, (string)$result['exception']);
        $this->assertSame('bad input', (string)$result['message']);
        $this->assertArrayHasKey('magento_debug_info', $result);
        $this->assertSame('session-id', $result['magento_debug_info']['session']);
    }

    #[Test]
    public function two_char_country_is_mapped_to_iso3_before_validation(): void
    {
        $client = $this->createMock(PostcodeApiClient::class);
        $client->expects($this->once())
            ->method('validateAddress')
            ->with('nld', '1234AB')
            ->willReturn(['matches' => []]);

        $helper = $this->createAvailableHelper(
            [(object)['iso2' => 'NL', 'iso3' => 'nld']],
            ['client' => $client]
        );

        $result = $helper->validateAddress('NL', '1234AB');

        $this->assertSame(['matches' => []], $result);
    }

    #[Test]
    public function unknown_two_char_country_is_sent_as_invalid(): void
    {
        $client = $this->createMock(PostcodeApiClient::class);
        $client->expects($this->once())
            ->method('validateAddress')
            ->with('INVALID', '1234AB')
            ->willReturn(['matches' => []]);

        $helper = $this->createAvailableHelper(
            [(object)['iso2' => 'DE', 'iso3' => 'deu']],
            ['client' => $client]
        );

        $result = $helper->validateAddress('XX', '1234AB');

        $this->assertSame(['matches' => []], $result);
    }

    #[Test]
    public function longer_country_passes_through_unchanged(): void
    {
        $client = $this->createMock(PostcodeApiClient::class);
        $client->expects($this->once())
            ->method('validateAddress')
            ->with('nld')
            ->willReturn(['matches' => []]);

        $helper = $this->createAvailableHelper([], ['client' => $client]);

        $result = $helper->validateAddress('nld');

        $this->assertSame(['matches' => []], $result);
    }

    #[Test]
    public function no_arguments_returns_error_payload(): void
    {
        $helper = $this->createAvailableHelper([]);

        $result = $helper->validateAddress();

        $this->assertTrue($result['error']);
    }

    #[Test]
    #[DataProvider('validationLevelProvider')]
    public function building_level_matches_receive_region_and_street_lines(string $level, bool $enriched): void
    {
        $match = [
            'status' => ['validationLevel' => $level],
            'country' => ['iso2Code' => 'NL'],
            'address' => ['street' => 'Damrak', 'building' => '1'],
            'details' => ['nldProvince' => ['name' => 'North Holland']],
        ];

        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('validateAddress')->willReturn(['matches' => [$match]]);

        $addressHelper = $this->createStub(AddressHelper::class);
        $addressHelper->method('getStreetLines')->willReturn(1);

        $helper = $this->createAvailableHelper([], [
            'client' => $client,
            'addressHelper' => $addressHelper,
            'regionFactory' => $this->createRegionFactory([
                'NL:name:North Holland' => ['found' => true, 'id' => 11, 'name' => 'North Holland'],
            ]),
        ]);

        $result = $helper->validateAddress('nld', '1234AB');

        if ($enriched) {
            $this->assertSame(['id' => 11, 'name' => 'North Holland'], $result['matches'][0]['region']);
            $this->assertSame(['Damrak 1'], $result['matches'][0]['streetLines']);

            return;
        }

        $this->assertArrayNotHasKey('region', $result['matches'][0]);
        $this->assertArrayNotHasKey('streetLines', $result['matches'][0]);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function validationLevelProvider(): array
    {
        return [
            'building' => ['Building', true],
            'partial building' => ['BuildingPartial', true],
            'street' => ['Street', false],
            'exact' => ['Exact', false],
        ];
    }

    #[Test]
    public function validation_failure_is_handled_through_shared_exception_path(): void
    {
        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('validateAddress')->willThrowException(new BadRequestException('bad'));

        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('setHttpResponseCode')->with(400);

        $helper = $this->createAvailableHelper([], [
            'client' => $client,
            'response' => $response,
        ]);

        $result = $helper->validateAddress('nld');

        $this->assertTrue($result['error']);
        $this->assertSame('Something went wrong. Please try again.', (string)$result['message']);
        $this->assertArrayNotHasKey('exception', $result);
        $this->assertArrayNotHasKey('magento_debug_info', $result);
    }

    #[Test]
    public function account_info_is_returned_from_client(): void
    {
        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('accountInfo')->willReturn(['status' => 'active']);

        $helper = $this->createAvailableHelper([], ['client' => $client]);

        $this->assertSame(['status' => 'active'], $helper->getAccountInfo());
    }

    #[Test]
    public function account_info_failure_yields_empty_array(): void
    {
        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('accountInfo')->willThrowException(new RuntimeException('down'));

        $helper = $this->createAvailableHelper([], ['client' => $client]);

        $this->assertSame([], $helper->getAccountInfo());
    }

    #[Test]
    public function api_client_is_returned_when_monitor_available(): void
    {
        $client = $this->createStub(PostcodeApiClient::class);

        $helper = $this->createAvailableHelper([], ['client' => $client]);

        $this->assertSame($client, $helper->getApiClient());
    }

    #[Test]
    public function unavailable_monitor_blocks_api_client(): void
    {
        $helper = $this->createHelper([]);

        $this->expectException(ServiceUnavailableException::class);

        $helper->getApiClient();
    }

    /**
     * @param list<object{iso2: string, iso3: string}> $countries
     * @param array<string, object> $overrides
     * @return ApiClientHelper
     */
    private function createHelper(array $countries, array $overrides = []): ApiClientHelper
    {
        $storeConfigHelper = $overrides['storeConfigHelper'] ?? $this->createStub(StoreConfigHelper::class);
        $storeConfigHelper->method('getSupportedCountries')->willReturn($countries);

        $request = $overrides['request'] ?? $this->createStub(Request::class);
        $logger = $overrides['logger'] ?? $this->createStub(LoggerInterface::class);

        if (isset($overrides['context'])) {
            $context = $overrides['context'];
        } else {
            $context = $this->createStub(Context::class);
            $context->method('getRequest')->willReturn($request);
            $context->method('getLogger')->willReturn($logger);
        }

        return new ApiClientHelper(
            $overrides['moduleList'] ?? $this->createModuleList(),
            $overrides['developerHelper'] ?? $this->createStub(DeveloperHelper::class),
            $context,
            $request,
            $overrides['response'] ?? $this->createStub(Response::class),
            $overrides['client'] ?? $this->createStub(PostcodeApiClient::class),
            $overrides['localeResolver'] ?? $this->createStub(LocaleResolver::class),
            $storeConfigHelper,
            $overrides['productMetadata'] ?? $this->createStub(ProductMetadataInterface::class),
            $overrides['regionFactory'] ?? $this->createRegionFactory([]),
            $overrides['addressHelper'] ?? $this->createStub(AddressHelper::class),
            $logger,
            $overrides['availabilityMonitor'] ?? $this->createStub(ApiAvailabilityMonitor::class)
        );
    }

    /**
     * @param list<object{iso2: string, iso3: string}> $countries
     * @param array<string, object> $overrides
     * @return ApiClientHelper
     */
    private function createAvailableHelper(array $countries, array $overrides = []): ApiClientHelper
    {
        $overrides['availabilityMonitor'] ??= $this->createAvailableMonitor();

        return $this->createHelper($countries, $overrides);
    }

    private function createAvailableMonitor(): ApiAvailabilityMonitor
    {
        $monitor = $this->createStub(ApiAvailabilityMonitor::class);
        $monitor->method('isAvailable')->willReturn(true);

        return $monitor;
    }

    /**
     * @param array<string, array{name: string, setup_version?: string}> $modules
     */
    private function createModuleList(array $modules = []): ModuleListInterface
    {
        $moduleList = $this->createStub(ModuleListInterface::class);
        $moduleList->method('getAll')->willReturn($modules);

        return $moduleList;
    }

    /**
     * @param array{key: string, secret: string} $credentials
     */
    private function createStoreConfigHelper(
        bool $debug = false,
        bool $split = false,
        array $credentials = ['key' => 'key', 'secret' => 'secret-123456'],
        string $moduleVersion = '1.2.3'
    ): StoreConfigHelper {
        $helper = $this->createStub(StoreConfigHelper::class);
        $helper->method('isDebugging')->willReturn($debug);
        $helper->method('isSetFlag')->willReturn($split);
        $helper->method('getCredentials')->willReturn($credentials);
        $helper->method('getModuleVersion')->willReturn($moduleVersion);

        return $helper;
    }

    /**
     * @param array<string, array{found: bool, id?: int|null, name?: string|null}> $lookups
     */
    private function createRegionFactory(array $lookups): RegionFactory
    {
        $factory = $this->createStub(RegionFactory::class);
        $factory->method('create')->willReturnCallback(
            function () use ($lookups) {
                $region = $this->createStub(Region::class);
                $resolve = function (array $data) {
                    $result = $this->createStub(Region::class);
                    $result->method('hasData')->willReturn($data['found'] ?? false);
                    $result->method('getId')->willReturn($data['id'] ?? null);
                    $result->method('getName')->willReturn($data['name'] ?? null);

                    return $result;
                };
                $region->method('loadByName')->willReturnCallback(
                    fn (string $name, string $country) => $resolve($lookups["$country:name:$name"] ?? ['found' => false])
                );
                $region->method('loadByCode')->willReturnCallback(
                    fn (string $code, string $country) => $resolve($lookups["$country:code:$code"] ?? ['found' => false])
                );

                return $region;
            }
        );

        return $factory;
    }
}
