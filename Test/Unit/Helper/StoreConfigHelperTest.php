<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Helper;

use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Developer\Helper\Data as DeveloperHelper;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ScopeInterface as AppScopeInterface;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Model\Config\Source\NlInputBehavior;
use PostcodeEu\AddressValidation\Model\Config\Source\ShowHideAddressFields;
use Psr\Log\LoggerInterface;

/**
 * Path aliases, scope resolution, credentials, countries and JS init config for StoreConfigHelper.
 */
class StoreConfigHelperTest extends TestCase
{
    private const SUPPORTED_COUNTRIES_JSON = '[{"iso2":"NL","iso3":"nld"},{"iso2":"BE","iso3":"bel"}]';

    private const MULTI_COUNTRY_JSON = '[{"iso2":"NL","iso3":"nld"},{"iso2":"BE","iso3":"bel"},'
        . '{"iso2":"DE","iso3":"deu"},{"iso2":"FR","iso3":"fra"}]';

    #[Test]
    public function frontend_init_config_includes_api_urls_and_flags(): void
    {
        $helper = $this->createHelper([
            StoreConfigHelper::PATH['supported_countries'] => self::SUPPORTED_COUNTRIES_JSON,
            StoreConfigHelper::PATH['disabled_countries'] => 'BE',
            StoreConfigHelper::PATH['allow_pobox_shipping'] => '1',
            StoreConfigHelper::PATH['change_fields_position'] => '1',
            StoreConfigHelper::PATH['split_street_values'] => '1',
        ]);

        $jsinit = $helper->getJsinit();

        $this->assertSame(
            [
                'enabled_countries',
                'nl_input_behavior',
                'show_hide_address_fields',
                'base_url',
                'api_actions',
                'debug',
                'change_fields_position',
                'allow_pobox_shipping',
                'split_street_values',
            ],
            array_keys($jsinit)
        );
        $this->assertSame(['NL'], $jsinit['enabled_countries']);
        $this->assertSame(NlInputBehavior::ZIP_HOUSE, $jsinit['nl_input_behavior']);
        $this->assertSame(ShowHideAddressFields::SHOW, $jsinit['show_hide_address_fields']);
        $this->assertFalse($jsinit['debug']);
        $this->assertTrue($jsinit['allow_pobox_shipping']);
        $this->assertTrue($jsinit['change_fields_position']);
        $this->assertTrue($jsinit['split_street_values']);
        $this->assertSame(
            [
                'dutchAddressLookup' => 'https://example.com/postcode-eu/V1/nl/address/{postcode}/{houseNumber}?form_key=FORMKEY',
                'autocomplete' => 'https://example.com/postcode-eu/V1/international/autocomplete/{context}/{term}?form_key=FORMKEY',
                'addressDetails' => 'https://example.com/postcode-eu/V1/international/address/{context}?form_key=FORMKEY',
                'validate' => 'https://example.com/postcode-eu/V1/international/validate/{country}?form_key=FORMKEY',
            ],
            $jsinit['api_actions']
        );
    }

    #[Test]
    public function init_config_reflects_configured_behavior_and_debug_flag(): void
    {
        $developerHelper = $this->createStub(DeveloperHelper::class);
        $developerHelper->method('isDevAllowed')->willReturn(true);

        $helper = $this->createHelper([
            StoreConfigHelper::PATH['supported_countries'] => self::SUPPORTED_COUNTRIES_JSON,
            StoreConfigHelper::PATH['nl_input_behavior'] => NlInputBehavior::FREE,
            StoreConfigHelper::PATH['show_hide_address_fields'] => ShowHideAddressFields::FORMAT,
            StoreConfigHelper::PATH['api_debug'] => '1',
        ], 'frontend', ['developerHelper' => $developerHelper]);

        $jsinit = $helper->getJsinit();

        $this->assertSame(NlInputBehavior::FREE, $jsinit['nl_input_behavior']);
        $this->assertSame(ShowHideAddressFields::FORMAT, $jsinit['show_hide_address_fields']);
        $this->assertTrue($jsinit['debug']);
    }

    #[Test]
    public function admin_init_config_uses_backend_api_proxy(): void
    {
        $helper = $this->createHelper(
            [StoreConfigHelper::PATH['supported_countries'] => self::SUPPORTED_COUNTRIES_JSON],
            FrontNameResolver::AREA_CODE
        );

        $backendUrl = 'https://admin.example.com/postcode_eu/address/api/';

        $this->assertSame(
            [
                'dutchAddressLookup' => $backendUrl . 'method/postcode/postcode/{postcode}/house_number/{houseNumber}',
                'autocomplete' => $backendUrl . 'method/autocomplete/context/{context}/term/{term}',
                'addressDetails' => $backendUrl . 'method/address_details/context/{context}',
            ],
            $helper->getJsinit()['api_actions']
        );
    }

    #[Test]
    public function known_alias_resolves_to_full_config_path(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(StoreConfigHelper::PATH['api_key'], ScopeInterface::SCOPE_STORES, null)
            ->willReturn('KEY');

        $helper = $this->createHelper([], 'frontend', ['scopeConfig' => $scopeConfig]);

        $this->assertSame('KEY', $helper->getValue('api_key'));
    }

    #[Test]
    public function unknown_key_passes_through_unchanged(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('custom/raw/path', ScopeInterface::SCOPE_STORES, null)
            ->willReturn('VALUE');

        $helper = $this->createHelper([], 'frontend', ['scopeConfig' => $scopeConfig]);

        $this->assertSame('VALUE', $helper->getValue('custom/raw/path'));
    }

    #[Test]
    public function known_flag_alias_resolves_to_full_config_path(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(StoreConfigHelper::PATH['enabled'], ScopeInterface::SCOPE_STORES, null)
            ->willReturn(true);

        $helper = $this->createHelper([], 'frontend', ['scopeConfig' => $scopeConfig]);

        $this->assertTrue($helper->isSetFlag('enabled'));
    }

    #[Test]
    public function unknown_flag_key_passes_through_unchanged(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('custom/raw/flag', ScopeInterface::SCOPE_STORES, null)
            ->willReturn(false);

        $helper = $this->createHelper([], 'frontend', ['scopeConfig' => $scopeConfig]);

        $this->assertFalse($helper->isSetFlag('custom/raw/flag'));
    }

    #[Test]
    #[DataProvider('debuggingProvider')]
    public function debugging_requires_flag_and_developer_allowlist(
        bool $debugFlag,
        bool $devAllowed,
        bool $expected
    ): void {
        $developerHelper = $this->createStub(DeveloperHelper::class);
        $developerHelper->method('isDevAllowed')->willReturn($devAllowed);

        $helper = $this->createHelper([
            StoreConfigHelper::PATH['api_debug'] => $debugFlag ? '1' : null,
        ], 'frontend', ['developerHelper' => $developerHelper]);

        $this->assertSame($expected, $helper->isDebugging());
    }

    /**
     * @return array<string, array{bool, bool, bool}>
     */
    public static function debuggingProvider(): array
    {
        return [
            'flag on, dev allowed' => [true, true, true],
            'flag on, dev not allowed' => [true, false, false],
            'flag off, dev allowed' => [false, true, false],
            'flag off, dev not allowed' => [false, false, false],
        ];
    }

    #[Test]
    public function explicit_store_id_scopes_to_given_store(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(StoreConfigHelper::PATH['api_key'], ScopeInterface::SCOPE_STORES, 7)
            ->willReturn('KEY');

        $helper = $this->createHelper([], 'frontend', ['scopeConfig' => $scopeConfig]);

        $this->assertSame('KEY', $helper->getValue('api_key', 7));
    }

    #[Test]
    public function admin_area_takes_scope_from_store_request_param(): void
    {
        $request = $this->createRequest([ScopeInterface::SCOPE_STORE => '5']);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(StoreConfigHelper::PATH['api_key'], ScopeInterface::SCOPE_STORES, 5)
            ->willReturn('KEY');

        $helper = $this->createHelper([], FrontNameResolver::AREA_CODE, [
            'request' => $request,
            'scopeConfig' => $scopeConfig,
        ]);

        $this->assertSame('KEY', $helper->getValue('api_key'));
    }

    #[Test]
    public function admin_area_takes_scope_from_website_request_param(): void
    {
        $request = $this->createRequest([ScopeInterface::SCOPE_WEBSITE => '4']);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(StoreConfigHelper::PATH['api_key'], ScopeInterface::SCOPE_WEBSITES, 4)
            ->willReturn('KEY');

        $helper = $this->createHelper([], FrontNameResolver::AREA_CODE, [
            'request' => $request,
            'scopeConfig' => $scopeConfig,
        ]);

        $this->assertSame('KEY', $helper->getValue('api_key'));
    }

    #[Test]
    public function admin_area_without_scope_params_uses_default_scope_with_zero_code(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(StoreConfigHelper::PATH['api_key'], AppScopeInterface::SCOPE_DEFAULT, 0)
            ->willReturn('KEY');

        $helper = $this->createHelper([], FrontNameResolver::AREA_CODE, [
            'request' => $this->createRequest([]),
            'scopeConfig' => $scopeConfig,
        ]);

        $this->assertSame('KEY', $helper->getValue('api_key'));
    }

    #[Test]
    public function explicit_zero_store_id_scopes_to_store_zero(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(StoreConfigHelper::PATH['api_key'], ScopeInterface::SCOPE_STORES, 0)
            ->willReturn('KEY');

        $helper = $this->createHelper([], 'frontend', ['scopeConfig' => $scopeConfig]);

        $this->assertSame('KEY', $helper->getValue('api_key', 0));
    }

    #[Test]
    public function store_param_maps_to_store_scope(): void
    {
        $helper = $this->createHelper([], 'frontend', [
            'request' => $this->createRequest([ScopeInterface::SCOPE_STORE => '3']),
        ]);

        $this->assertSame([ScopeInterface::SCOPE_STORES, 3], $helper->getScopeFromRequest());
    }

    #[Test]
    public function website_param_maps_to_website_scope(): void
    {
        $helper = $this->createHelper([], 'frontend', [
            'request' => $this->createRequest([ScopeInterface::SCOPE_WEBSITE => '4']),
        ]);

        $this->assertSame([ScopeInterface::SCOPE_WEBSITES, 4], $helper->getScopeFromRequest());
    }

    #[Test]
    public function missing_scope_params_map_to_default_scope(): void
    {
        $helper = $this->createHelper([], 'frontend', ['request' => $this->createRequest([])]);

        $this->assertSame([AppScopeInterface::SCOPE_DEFAULT, 0], $helper->getScopeFromRequest());
    }

    #[Test]
    public function store_param_wins_over_website_param(): void
    {
        $helper = $this->createHelper([], 'frontend', [
            'request' => $this->createRequest([
                ScopeInterface::SCOPE_STORE => '3',
                ScopeInterface::SCOPE_WEBSITE => '4',
            ]),
        ]);

        $this->assertSame([ScopeInterface::SCOPE_STORES, 3], $helper->getScopeFromRequest());
    }

    #[Test]
    public function secret_with_colon_is_decrypted(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())
            ->method('decrypt')
            ->with('0:encrypted')
            ->willReturn('plainsecret');

        $helper = $this->createHelper([
            StoreConfigHelper::PATH['api_key'] => 'KEY',
            StoreConfigHelper::PATH['api_secret'] => '0:encrypted',
        ], 'frontend', ['encryptor' => $encryptor]);

        $this->assertSame(['key' => 'KEY', 'secret' => 'plainsecret'], $helper->getCredentials());
    }

    #[Test]
    public function secret_without_colon_is_not_decrypted(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->never())->method('decrypt');

        $helper = $this->createHelper([
            StoreConfigHelper::PATH['api_key'] => 'KEY',
            StoreConfigHelper::PATH['api_secret'] => 'plainsecret',
        ], 'frontend', ['encryptor' => $encryptor]);

        $this->assertSame(['key' => 'KEY', 'secret' => 'plainsecret'], $helper->getCredentials());
    }

    #[Test]
    #[DataProvider('missingCredentialConfigProvider')]
    public function missing_credentials_return_empty_strings(array $config, array $expected): void
    {
        $helper = $this->createHelper($config);

        $this->assertSame($expected, $helper->getCredentials());
    }

    /**
     * @return array<string, array{array<string, string>, array{key: string, secret: string}}>
     */
    public static function missingCredentialConfigProvider(): array
    {
        return [
            'both missing' => [[], ['key' => '', 'secret' => '']],
            'key missing' => [
                [StoreConfigHelper::PATH['api_secret'] => 'secret'],
                ['key' => '', 'secret' => 'secret'],
            ],
            'secret missing' => [
                [StoreConfigHelper::PATH['api_key'] => 'key'],
                ['key' => 'key', 'secret' => ''],
            ],
        ];
    }

    #[Test]
    #[DataProvider('credentialPresenceProvider')]
    public function credential_presence_follows_isset_semantics(array $config, bool $expected): void
    {
        // Pins surprising behaviour: hasCredentials() uses isset semantics, so empty-string credentials count as present.
        $helper = $this->createHelper($config);

        $this->assertSame($expected, $helper->hasCredentials());
    }

    /**
     * @return array<string, array{array<string, string>, bool}>
     */
    public static function credentialPresenceProvider(): array
    {
        return [
            'both present' => [
                [StoreConfigHelper::PATH['api_key'] => 'key', StoreConfigHelper::PATH['api_secret'] => 'secret'],
                true,
            ],
            'key missing' => [[StoreConfigHelper::PATH['api_secret'] => 'secret'], false],
            'secret missing' => [[StoreConfigHelper::PATH['api_key'] => 'key'], false],
            'both missing' => [[], false],
            'empty strings count as present' => [
                [StoreConfigHelper::PATH['api_key'] => '', StoreConfigHelper::PATH['api_secret'] => ''],
                true,
            ],
        ];
    }

    #[Test]
    public function no_disabled_countries_returns_all_supported_iso2_codes(): void
    {
        $helper = $this->createHelper([
            StoreConfigHelper::PATH['supported_countries'] => self::MULTI_COUNTRY_JSON,
        ]);

        $this->assertSame(['NL', 'BE', 'DE', 'FR'], $helper->getEnabledCountries());
    }

    #[Test]
    public function disabled_countries_removed_and_reindexed(): void
    {
        $helper = $this->createHelper([
            StoreConfigHelper::PATH['supported_countries'] => self::MULTI_COUNTRY_JSON,
            StoreConfigHelper::PATH['disabled_countries'] => 'BE,DE',
        ]);

        $this->assertSame(['NL', 'FR'], $helper->getEnabledCountries());
    }

    #[Test]
    public function missing_supported_countries_config_returns_empty_array(): void
    {
        $helper = $this->createHelper([]);

        $this->assertSame([], $helper->getSupportedCountries());
    }

    #[Test]
    #[DataProvider('invalidSupportedCountriesProvider')]
    public function invalid_supported_countries_config_returns_empty_array(string $value): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $helper = $this->createHelper([
            StoreConfigHelper::PATH['supported_countries'] => $value,
        ], 'frontend', ['logger' => $logger]);

        $this->assertSame([], $helper->getSupportedCountries());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSupportedCountriesProvider(): array
    {
        return [
            'invalid json' => ['not-json'],
            'scalar json' => ['"hello"'],
        ];
    }

    #[Test]
    public function module_version_returned_when_configured(): void
    {
        $helper = $this->createHelper([
            StoreConfigHelper::PATH['module_version'] => '1.2.3',
        ]);

        $this->assertSame('1.2.3', $helper->getModuleVersion());
    }

    #[Test]
    public function missing_module_version_falls_back_to_unknown(): void
    {
        $helper = $this->createHelper([]);

        $this->assertSame('UNKNOWN', $helper->getModuleVersion());
    }

    /**
     * @param array<string, string|null> $config
     * @param string $areaCode
     * @param array{scopeConfig?: ScopeConfigInterface, request?: RequestInterface, encryptor?: EncryptorInterface, developerHelper?: DeveloperHelper, logger?: LoggerInterface} $overrides
     * @return StoreConfigHelper
     */
    private function createHelper(array $config = [], string $areaCode = 'frontend', array $overrides = []): StoreConfigHelper
    {
        if (isset($overrides['scopeConfig'])) {
            $scopeConfig = $overrides['scopeConfig'];
        } else {
            $scopeConfig = $this->createStub(ScopeConfigInterface::class);
            $scopeConfig->method('getValue')->willReturnCallback(
                function (string $path) use ($config) {
                    return $config[$path] ?? null;
                }
            );
            $scopeConfig->method('isSetFlag')->willReturnCallback(
                function (string $path) use ($config) {
                    return !empty($config[$path]);
                }
            );
        }

        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method('getBaseUrl')->willReturn('https://example.com/');

        $context = $this->createStub(Context::class);
        $context->method('getScopeConfig')->willReturn($scopeConfig);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);
        $context->method('getRequest')->willReturn(
            $overrides['request'] ?? $this->createStub(RequestInterface::class)
        );
        $context->method('getLogger')->willReturn(
            $overrides['logger'] ?? $this->createStub(LoggerInterface::class)
        );

        $store = $this->createStub(StoreInterface::class);
        $store->method('getCode')->willReturn('default');

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $appState = $this->createStub(AppState::class);
        $appState->method('getAreaCode')->willReturn($areaCode);

        $formKey = $this->createStub(FormKey::class);
        $formKey->method('getFormKey')->willReturn('FORMKEY');

        $backendUrl = $this->createStub(BackendUrlInterface::class);
        $backendUrl->method('getUrl')->willReturn('https://admin.example.com/postcode_eu/address/api/');

        return new StoreConfigHelper(
            $context,
            $storeManager,
            $overrides['developerHelper'] ?? $this->createStub(DeveloperHelper::class),
            $overrides['encryptor'] ?? $this->createStub(EncryptorInterface::class),
            $this->createStub(CountryCollectionFactory::class),
            $this->createStub(ResolverInterface::class),
            $formKey,
            $appState,
            $backendUrl
        );
    }

    /**
     * @param array<string, string> $params
     */
    private function createRequest(array $params): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            fn (string $param) => $params[$param] ?? null
        );

        return $request;
    }
}
