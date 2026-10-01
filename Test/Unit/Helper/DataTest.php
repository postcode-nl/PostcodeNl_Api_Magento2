<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Filesystem\DriverInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Helper\ApiClientHelper;
use PostcodeEu\AddressValidation\Helper\Data;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\HTTP\Client\Curl;
use PostcodeEu\AddressValidation\Model\Config\Source\NlInputBehavior;
use PostcodeEu\AddressValidation\Model\Config\Source\ShowHideAddressFields;
use PostcodeEu\AddressValidation\Service\ApiAvailabilityMonitor;
use PostcodeEu\AddressValidation\Service\Exception\CurlException;
use Psr\Log\LoggerInterface;

/**
 * Feature toggles and module update info for Data.
 */
class DataTest extends TestCase
{
    #[Test]
    #[DataProvider('disabledProvider')]
    public function disabled_state_follows_gates(
        bool $enabled,
        string $accountStatus,
        bool $monitorAvailable,
        bool $expected
    ): void {
        $helper = $this->createHelper([
            'storeConfigHelper' => $this->createConfigStub([
                'enabled' => $enabled,
                'account_status' => $accountStatus,
            ]),
            'monitor' => $this->createMonitor($monitorAvailable),
        ]);

        $this->assertSame($expected, $helper->isDisabled());
    }

    /**
     * @return array<string, array{bool, string, bool, bool}>
     */
    public static function disabledProvider(): array
    {
        return [
            'enabled with active account and available api' => [
                true,
                ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE,
                true,
                false,
            ],
            'disabled flag off' => [false, ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE, true, true],
            'account status not active' => [true, 'pending', true, true],
            'api monitor unavailable' => [true, ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE, false, true],
        ];
    }

    #[Test]
    #[DataProvider('nlComponentDisabledProvider')]
    public function nl_component_disabled_state_follows_gates(
        bool $enabled,
        array $enabledCountries,
        string $inputBehavior,
        bool $expected
    ): void {
        $helper = $this->createHelper([
            'storeConfigHelper' => $this->createConfigStub([
                'enabled' => $enabled,
                'enabled_countries' => $enabledCountries,
                'nl_input_behavior' => $inputBehavior,
            ]),
        ]);

        $this->assertSame($expected, $helper->isNlComponentDisabled());
    }

    /**
     * @return array<string, array{bool, list<string>, string, bool}>
     */
    public static function nlComponentDisabledProvider(): array
    {
        return [
            'module disabled' => [false, ['NL'], NlInputBehavior::ZIP_HOUSE, true],
            'netherlands not enabled' => [true, ['BE', 'DE'], NlInputBehavior::ZIP_HOUSE, true],
            'input behavior is free' => [true, ['NL'], NlInputBehavior::FREE, true],
            'enabled with nl and zip house behavior' => [true, ['NL'], NlInputBehavior::ZIP_HOUSE, false],
        ];
    }

    #[Test]
    #[DataProvider('formattedOutputDisabledProvider')]
    public function formatted_output_disabled_state_follows_gates(
        bool $enabled,
        string $showHideAddressFields,
        bool $expected
    ): void {
        $helper = $this->createHelper([
            'storeConfigHelper' => $this->createConfigStub([
                'enabled' => $enabled,
                'show_hide_address_fields' => $showHideAddressFields,
            ]),
        ]);

        $this->assertSame($expected, $helper->isFormattedOutputDisabled());
    }

    /**
     * @return array<string, array{bool, string, bool}>
     */
    public static function formattedOutputDisabledProvider(): array
    {
        return [
            'module disabled' => [false, ShowHideAddressFields::FORMAT, true],
            'fields not formatted' => [true, ShowHideAddressFields::SHOW, true],
            'fields formatted' => [true, ShowHideAddressFields::FORMAT, false],
        ];
    }

    #[Test]
    #[DataProvider('autofillBypassDisabledProvider')]
    public function autofill_bypass_disabled_state_follows_gates(
        bool $enabled,
        string $showHideAddressFields,
        bool $allowAutofillBypass,
        bool $expected
    ): void {
        $helper = $this->createHelper([
            'storeConfigHelper' => $this->createConfigStub([
                'enabled' => $enabled,
                'show_hide_address_fields' => $showHideAddressFields,
                'allow_autofill_bypass' => $allowAutofillBypass,
            ]),
        ]);

        $this->assertSame($expected, $helper->isAutofillBypassDisabled());
    }

    /**
     * @return array<string, array{bool, string, bool, bool}>
     */
    public static function autofillBypassDisabledProvider(): array
    {
        return [
            'module disabled' => [false, ShowHideAddressFields::FORMAT, true, true],
            'fields shown' => [true, ShowHideAddressFields::SHOW, true, true],
            'bypass flag off' => [true, ShowHideAddressFields::FORMAT, false, true],
            'enabled with bypass allowed' => [true, ShowHideAddressFields::FORMAT, true, false],
        ];
    }

    #[Test]
    #[DataProvider('moduleInfoProvider')]
    public function module_info_compares_latest_packagist_version(string $latestVersion, bool $hasUpdate): void
    {
        $helper = $this->createHelper([
            'storeConfigHelper' => $this->createConfigStub(['module_version' => '1.2.3']),
            'dir' => $this->createVarDir(),
            'fs' => $this->createFilesystem(),
            'curl' => $this->createPackagistCurl(200, $this->packagistBody($latestVersion)),
        ]);

        $info = $helper->getModuleInfo();

        $this->assertSame('1.2.3', $info['version']);
        $this->assertSame($latestVersion, $info['latest_version']);
        $this->assertSame($hasUpdate, $info['has_update']);
        $this->assertSame(Data::MODULE_RELEASE_URL, $info['release_url']);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function moduleInfoProvider(): array
    {
        return [
            'newer latest release available' => ['1.3.0', true],
            'latest equals current version' => ['1.2.3', false],
            'latest older than current version' => ['1.1.0', false],
        ];
    }

    #[Test]
    public function packagist_failure_falls_back_to_current_version(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to get package data:', $this->arrayHasKey('exception'));

        $curl = $this->createStub(Curl::class);
        $curl->method('get')->willThrowException(new CurlException('connection reset'));

        $helper = $this->createHelper([
            'storeConfigHelper' => $this->createConfigStub(['module_version' => '1.2.3']),
            'dir' => $this->createVarDir(),
            'fs' => $this->createFilesystem(),
            'curl' => $curl,
            'logger' => $logger,
        ]);

        $info = $helper->getModuleInfo();

        $this->assertSame('1.2.3', $info['version']);
        $this->assertSame('1.2.3', $info['latest_version']);
        $this->assertFalse($info['has_update']);
        $this->assertSame(Data::MODULE_RELEASE_URL, $info['release_url']);
    }

    #[Test]
    public function unexpected_packagist_shape_falls_back_to_current_version(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to get package data:', $this->arrayHasKey('exception'));

        $helper = $this->createHelper([
            'storeConfigHelper' => $this->createConfigStub(['module_version' => '1.2.3']),
            'dir' => $this->createVarDir(),
            'fs' => $this->createFilesystem(),
            'curl' => $this->createPackagistCurl(200, '{"packages":{"other/package":[{"version":"1.4.0"}]}}'),
            'logger' => $logger,
        ]);

        $info = $helper->getModuleInfo();

        $this->assertSame('1.2.3', $info['latest_version']);
        $this->assertFalse($info['has_update']);
    }

    #[Test]
    public function non_array_packagist_json_falls_back_to_current_version(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to get package data:', $this->arrayHasKey('exception'));

        $helper = $this->createHelper([
            'storeConfigHelper' => $this->createConfigStub(['module_version' => '1.2.3']),
            'dir' => $this->createVarDir(),
            'fs' => $this->createFilesystem(),
            'curl' => $this->createPackagistCurl(200, '"hello"'),
            'logger' => $logger,
        ]);

        $info = $helper->getModuleInfo();

        $this->assertSame('1.2.3', $info['latest_version']);
        $this->assertFalse($info['has_update']);
    }

    #[Test]
    public function stat_failure_skips_if_modified_since_header(): void
    {
        $fs = $this->createStub(DriverInterface::class);
        $fs->method('isDirectory')->willReturn(true);
        $fs->method('isExists')->willReturn(true);
        $fs->method('stat')->willReturn(false);
        $fs->method('filePutContents')->willReturn(true);

        $curl = $this->createMock(Curl::class);
        $curl->expects($this->never())->method('setHeaders');
        $curl->method('getStatus')->willReturn(200);
        $curl->method('getBody')->willReturn($this->packagistBody('1.4.0'));

        $helper = $this->createHelper([
            'storeConfigHelper' => $this->createConfigStub(['module_version' => '1.2.3']),
            'dir' => $this->createVarDir(),
            'fs' => $fs,
            'curl' => $curl,
        ]);

        $info = $helper->getModuleInfo();

        $this->assertSame('1.4.0', $info['latest_version']);
        $this->assertTrue($info['has_update']);
    }

    #[Test]
    public function not_modified_response_reads_cached_package_data(): void
    {
        $helper = $this->createHelper([
            'storeConfigHelper' => $this->createConfigStub(['module_version' => '1.2.3']),
            'dir' => $this->createVarDir(),
            'fs' => $this->createFilesystem(true, true, ['mtime' => 1700000000], $this->packagistBody('1.4.0')),
            'curl' => $this->createPackagistCurl(304),
        ]);

        $info = $helper->getModuleInfo();

        $this->assertSame('1.4.0', $info['latest_version']);
        $this->assertTrue($info['has_update']);
    }

    #[Test]
    public function unexpected_packagist_status_falls_back_to_current_version(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to get package data:', $this->arrayHasKey('exception'));

        $helper = $this->createHelper([
            'storeConfigHelper' => $this->createConfigStub(['module_version' => '1.2.3']),
            'dir' => $this->createVarDir(),
            'fs' => $this->createFilesystem(),
            'curl' => $this->createPackagistCurl(503),
            'logger' => $logger,
        ]);

        $info = $helper->getModuleInfo();

        $this->assertSame('1.2.3', $info['latest_version']);
        $this->assertFalse($info['has_update']);
    }

    /**
     * @param array<string, object> $overrides
     */
    private function createHelper(array $overrides = []): Data
    {
        $storeConfigHelper = $overrides['storeConfigHelper'] ?? $this->createStub(StoreConfigHelper::class);
        $dir = $overrides['dir'] ?? $this->createStub(DirectoryList::class);
        $fs = $overrides['fs'] ?? $this->createStub(DriverInterface::class);
        $curl = $overrides['curl'] ?? $this->createStub(Curl::class);
        $monitor = $overrides['monitor'] ?? $this->createMonitor(true);
        $logger = $overrides['logger'] ?? $this->createStub(LoggerInterface::class);
        $request = $overrides['request'] ?? $this->createStub(RequestInterface::class);

        if (isset($overrides['context'])) {
            $context = $overrides['context'];
        } else {
            $context = $this->createStub(Context::class);
            $context->method('getRequest')->willReturn($request);
            $context->method('getLogger')->willReturn($logger);
        }

        return new Data($context, $storeConfigHelper, $dir, $fs, $curl, $monitor);
    }

    /**
     * @param array{
     *     enabled?: bool,
     *     account_status?: string,
     *     enabled_countries?: list<string>,
     *     nl_input_behavior?: string,
     *     show_hide_address_fields?: string,
     *     allow_autofill_bypass?: bool,
     *     module_version?: string
     * } $config
     */
    private function createConfigStub(array $config = []): StoreConfigHelper
    {
        $values = [
            'account_status' => $config['account_status'] ?? ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE,
            'nl_input_behavior' => $config['nl_input_behavior'] ?? NlInputBehavior::ZIP_HOUSE,
            'show_hide_address_fields' => $config['show_hide_address_fields'] ?? ShowHideAddressFields::FORMAT,
        ];

        $helper = $this->createStub(StoreConfigHelper::class);
        $helper->method('isEnabled')->willReturn($config['enabled'] ?? true);
        $helper->method('getEnabledCountries')->willReturn($config['enabled_countries'] ?? ['NL']);
        $helper->method('isSetFlag')->willReturn($config['allow_autofill_bypass'] ?? true);
        $helper->method('getValue')->willReturnCallback(
            fn (string $path) => $values[$path] ?? null
        );
        $helper->method('getModuleVersion')->willReturn($config['module_version'] ?? '1.2.3');

        return $helper;
    }

    private function createMonitor(bool $available): ApiAvailabilityMonitor
    {
        $monitor = $this->createStub(ApiAvailabilityMonitor::class);
        $monitor->method('isAvailable')->willReturn($available);

        return $monitor;
    }

    private function createVarDir(): DirectoryList
    {
        $dir = $this->createStub(DirectoryList::class);
        $dir->method('getPath')->willReturn('/var');

        return $dir;
    }

    /**
     * @param array{mtime: int|false} $stat
     */
    private function createFilesystem(
        bool $fileExists = false,
        bool $putSucceeds = true,
        array $stat = ['mtime' => false],
        string $contents = ''
    ): DriverInterface {
        $fs = $this->createStub(DriverInterface::class);
        $fs->method('isDirectory')->willReturn(true);
        $fs->method('isExists')->willReturn($fileExists);
        $fs->method('filePutContents')->willReturn($putSucceeds);
        $fs->method('stat')->willReturn($stat);
        $fs->method('fileGetContents')->willReturn($contents);

        return $fs;
    }

    private function createPackagistCurl(int $status, string $body = ''): Curl
    {
        $curl = $this->createStub(Curl::class);
        $curl->method('getStatus')->willReturn($status);
        $curl->method('getBody')->willReturn($body);

        return $curl;
    }

    private function packagistBody(string $latestVersion): string
    {
        return json_encode(
            ['packages' => [Data::VENDOR_PACKAGE => [['version' => $latestVersion]]]],
            JSON_THROW_ON_ERROR
        );
    }
}
