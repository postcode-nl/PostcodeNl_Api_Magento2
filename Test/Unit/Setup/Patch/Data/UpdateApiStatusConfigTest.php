<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Setup\Patch\Data;

use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Helper\ApiClientHelper;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Service\Exception\AuthenticationException;
use PostcodeEu\AddressValidation\Service\PostcodeApiClient;
use PostcodeEu\AddressValidation\Setup\Patch\Data\UpdateApiStatusConfig;

/**
 * Credential migration and status derivation for UpdateApiStatusConfig.
 */
class UpdateApiStatusConfigTest extends TestCase
{
    #[Test]
    #[DataProvider('incompleteCredentialsProvider')]
    public function scope_with_incomplete_credentials_is_skipped(array $rows): void
    {
        $connection = $this->createConnection($rows);
        $connection->expects($this->never())->method('fetchOne');

        $apiClientHelper = $this->createMock(ApiClientHelper::class);
        $apiClientHelper->expects($this->never())->method('getApiClient');

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->never())->method('saveConfig');
        $resourceConfig->expects($this->never())->method('deleteConfig');

        $patch = $this->createPatch($connection, $apiClientHelper, $resourceConfig);

        $patch->apply();
    }

    /**
     * @return array<string, array{array<int, array<string, mixed>>}>
     */
    public static function incompleteCredentialsProvider(): array
    {
        return [
            'missing secret' => [[
                ['scope' => 'default', 'scope_id' => 0, 'path' => StoreConfigHelper::PATH['api_key'], 'value' => 'key'],
            ]],
            'missing key' => [[
                ['scope' => 'stores', 'scope_id' => 1, 'path' => StoreConfigHelper::PATH['api_secret'], 'value' => 'secret'],
            ]],
            'empty secret' => [[
                ['scope' => 'default', 'scope_id' => 0, 'path' => StoreConfigHelper::PATH['api_key'], 'value' => 'key'],
                ['scope' => 'default', 'scope_id' => 0, 'path' => StoreConfigHelper::PATH['api_secret'], 'value' => ''],
            ]],
        ];
    }

    #[Test]
    public function already_migrated_scope_is_skipped(): void
    {
        $connection = $this->createConnection(self::credentialRows('default', 0));
        $connection->expects($this->once())->method('fetchOne')->willReturn('active');

        $apiClientHelper = $this->createMock(ApiClientHelper::class);
        $apiClientHelper->expects($this->never())->method('getApiClient');

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->never())->method('saveConfig');
        // Pins source issue: the already-migrated early return also skips deleteConfig(), so the obsolete
        // postcodenl_api/general/* paths are never cleaned once a scope has been migrated.
        $resourceConfig->expects($this->never())->method('deleteConfig');

        $patch = $this->createPatch($connection, $apiClientHelper, $resourceConfig);

        $patch->apply();
    }

    #[Test]
    public function active_account_saves_name_active_status_and_supported_countries(): void
    {
        $saved = [];

        $connection = $this->createConnection(self::credentialRows('stores', 5));
        $connection->expects($this->once())->method('fetchOne')->willReturn(false);

        $client = $this->createMock(PostcodeApiClient::class);
        $client->expects($this->once())->method('setCredentials')->with('key', 'secret');
        $client->expects($this->once())
            ->method('accountInfo')
            ->willReturn(['name' => 'Postcode.eu', 'hasAccess' => true]);
        $client->expects($this->once())
            ->method('internationalGetSupportedCountries')
            ->willReturn(['NL', 'BE']);

        $apiClientHelper = $this->createMock(ApiClientHelper::class);
        $apiClientHelper->expects($this->once())->method('getApiClient')->willReturn($client);

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->exactly(3))
            ->method('saveConfig')
            ->willReturnCallback(
                function (string $path, string $value, string $scope, int $scopeId) use (&$saved): void {
                    $saved[] = [$path, $value, $scope, $scopeId];
                }
            );
        $resourceConfig->expects($this->exactly(3))->method('deleteConfig');

        $patch = $this->createPatch($connection, $apiClientHelper, $resourceConfig);

        $patch->apply();

        $this->assertSame([
            [StoreConfigHelper::PATH['account_name'], 'Postcode.eu', 'stores', 5],
            [StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE, 'stores', 5],
            [StoreConfigHelper::PATH['supported_countries'], '["NL","BE"]', 'stores', 5],
        ], $saved);
    }

    #[Test]
    public function account_info_missing_keys_saves_empty_name_and_inactive_status(): void
    {
        $saved = [];

        $connection = $this->createConnection(self::credentialRows('stores', 5));
        $connection->expects($this->once())->method('fetchOne')->willReturn(false);

        $client = $this->createMock(PostcodeApiClient::class);
        $client->expects($this->once())->method('setCredentials')->with('key', 'secret');
        $client->expects($this->once())->method('accountInfo')->willReturn([]);
        $client->expects($this->never())->method('internationalGetSupportedCountries');

        $apiClientHelper = $this->createMock(ApiClientHelper::class);
        $apiClientHelper->expects($this->once())->method('getApiClient')->willReturn($client);

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->exactly(2))
            ->method('saveConfig')
            ->willReturnCallback(
                function (string $path, string $value, string $scope, int $scopeId) use (&$saved): void {
                    $saved[] = [$path, $value, $scope, $scopeId];
                }
            );
        $resourceConfig->expects($this->exactly(3))->method('deleteConfig');

        $patch = $this->createPatch($connection, $apiClientHelper, $resourceConfig);

        $patch->apply();

        $this->assertSame([
            [StoreConfigHelper::PATH['account_name'], '', 'stores', 5],
            [StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE, 'stores', 5],
        ], $saved);
    }

    #[Test]
    public function inactive_account_saves_name_and_inactive_status(): void
    {
        $saved = [];

        $connection = $this->createConnection(self::credentialRows('default', 0));
        $connection->expects($this->once())->method('fetchOne')->willReturn(false);

        $client = $this->createMock(PostcodeApiClient::class);
        $client->expects($this->once())
            ->method('accountInfo')
            ->willReturn(['name' => 'Postcode.eu', 'hasAccess' => false]);
        $client->expects($this->once())->method('setCredentials')->with('key', 'secret');
        $client->expects($this->never())->method('internationalGetSupportedCountries');

        $apiClientHelper = $this->createMock(ApiClientHelper::class);
        $apiClientHelper->expects($this->once())->method('getApiClient')->willReturn($client);

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->exactly(2))
            ->method('saveConfig')
            ->willReturnCallback(
                function (string $path, string $value, string $scope, int $scopeId) use (&$saved): void {
                    $saved[] = [$path, $value, $scope, $scopeId];
                }
            );
        $resourceConfig->expects($this->exactly(3))->method('deleteConfig');

        $patch = $this->createPatch($connection, $apiClientHelper, $resourceConfig);

        $patch->apply();

        $this->assertSame([
            [StoreConfigHelper::PATH['account_name'], 'Postcode.eu', 'default', 0],
            [StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE, 'default', 0],
        ], $saved);
    }

    #[Test]
    public function authentication_failure_saves_invalid_credentials_without_account_name(): void
    {
        $saved = [];

        $connection = $this->createConnection(self::credentialRows('stores', 5));
        $connection->expects($this->once())->method('fetchOne')->willReturn(false);

        $client = $this->createMock(PostcodeApiClient::class);
        $client->expects($this->once())
            ->method('accountInfo')
            ->willThrowException(new AuthenticationException('Invalid credentials'));
        $client->expects($this->once())->method('setCredentials')->with('key', 'secret');

        $apiClientHelper = $this->createMock(ApiClientHelper::class);
        $apiClientHelper->expects($this->once())->method('getApiClient')->willReturn($client);

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->once())
            ->method('saveConfig')
            ->willReturnCallback(
                function (string $path, string $value, string $scope, int $scopeId) use (&$saved): void {
                    $saved[] = [$path, $value, $scope, $scopeId];
                }
            );
        $resourceConfig->expects($this->exactly(3))->method('deleteConfig');

        $patch = $this->createPatch($connection, $apiClientHelper, $resourceConfig);

        $patch->apply();

        $this->assertSame([
            [
                StoreConfigHelper::PATH['account_status'],
                ApiClientHelper::API_ACCOUNT_STATUS_INVALID_CREDENTIALS,
                'stores',
                5,
            ],
        ], $saved);
    }

    #[Test]
    public function obsolete_paths_are_deleted_for_processed_scope(): void
    {
        $deleted = [];

        $connection = $this->createConnection(self::credentialRows('default', 0));
        $connection->expects($this->once())->method('fetchOne')->willReturn(false);

        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('accountInfo')->willReturn(['name' => 'Postcode.eu', 'hasAccess' => false]);

        $apiClientHelper = $this->createStub(ApiClientHelper::class);
        $apiClientHelper->method('getApiClient')->willReturn($client);

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->exactly(3))
            ->method('deleteConfig')
            ->willReturnCallback(
                function (string $path, string $scope, int $scopeId) use (&$deleted): void {
                    $deleted[] = [$path, $scope, $scopeId];
                }
            );

        $patch = $this->createPatch($connection, $apiClientHelper, $resourceConfig);

        $patch->apply();

        $this->assertSame([
            ['postcodenl_api/general/api_key_is_valid', 'default', 0],
            ['postcodenl_api/general/supported_countries', 'default', 0],
            ['postcodenl_api/general/account_name', 'default', 0],
        ], $deleted);
    }

    #[Test]
    public function grouped_scopes_are_processed_independently(): void
    {
        $saved = [];

        $rows = array_merge(
            self::credentialRows('default', 0),
            self::credentialRows('websites', 2)
        );

        $connection = $this->createConnection($rows);
        $connection->expects($this->exactly(2))->method('fetchOne')->willReturn(false);

        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('accountInfo')->willReturn(['name' => 'Postcode.eu', 'hasAccess' => false]);

        $apiClientHelper = $this->createStub(ApiClientHelper::class);
        $apiClientHelper->method('getApiClient')->willReturn($client);

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->exactly(4))
            ->method('saveConfig')
            ->willReturnCallback(
                function (string $path, string $value, string $scope, int $scopeId) use (&$saved): void {
                    $saved[] = [$path, $value, $scope, $scopeId];
                }
            );
        $resourceConfig->expects($this->exactly(6))->method('deleteConfig');

        $patch = $this->createPatch($connection, $apiClientHelper, $resourceConfig);

        $patch->apply();

        $this->assertSame([
            [StoreConfigHelper::PATH['account_name'], 'Postcode.eu', 'default', 0],
            [StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE, 'default', 0],
            [StoreConfigHelper::PATH['account_name'], 'Postcode.eu', 'websites', 2],
            [StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE, 'websites', 2],
        ], $saved);
    }

    #[Test]
    public function incomplete_scope_does_not_block_complete_scope(): void
    {
        $saved = [];

        $rows = array_merge(
            [['scope' => 'default', 'scope_id' => 0, 'path' => StoreConfigHelper::PATH['api_key'], 'value' => 'key']],
            self::credentialRows('stores', 5)
        );

        $connection = $this->createConnection($rows);
        $connection->expects($this->once())->method('fetchOne')->willReturn(false);

        $client = $this->createStub(PostcodeApiClient::class);
        $client->method('accountInfo')->willReturn(['name' => 'Postcode.eu', 'hasAccess' => false]);

        $apiClientHelper = $this->createStub(ApiClientHelper::class);
        $apiClientHelper->method('getApiClient')->willReturn($client);

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->exactly(2))
            ->method('saveConfig')
            ->willReturnCallback(
                function (string $path, string $value, string $scope, int $scopeId) use (&$saved): void {
                    $saved[] = [$path, $value, $scope, $scopeId];
                }
            );
        $resourceConfig->expects($this->exactly(3))->method('deleteConfig');

        $patch = $this->createPatch($connection, $apiClientHelper, $resourceConfig);

        $patch->apply();

        $this->assertSame([
            [StoreConfigHelper::PATH['account_name'], 'Postcode.eu', 'stores', 5],
            [StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE, 'stores', 5],
        ], $saved);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function credentialRows(string $scope, int $scopeId): array
    {
        return [
            ['scope' => $scope, 'scope_id' => $scopeId, 'path' => StoreConfigHelper::PATH['api_key'], 'value' => 'key'],
            ['scope' => $scope, 'scope_id' => $scopeId, 'path' => StoreConfigHelper::PATH['api_secret'], 'value' => 'secret'],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function createPatch(
        AdapterInterface $connection,
        ApiClientHelper $apiClientHelper,
        Config $resourceConfig
    ): UpdateApiStatusConfig {
        $resourceConfig->method('getConnection')->willReturn($connection);
        $resourceConfig->method('getTable')->willReturn('core_config_data');

        return new UpdateApiStatusConfig(
            $apiClientHelper,
            $this->createStub(WriterInterface::class),
            $this->createStub(StoreConfigHelper::class),
            $resourceConfig
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function createConnection(array $rows): AdapterInterface
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('startSetup');
        $connection->expects($this->once())->method('endSetup');
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn($rows);

        return $connection;
    }
}
