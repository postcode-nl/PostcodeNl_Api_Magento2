<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Setup\Patch\Data;

use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Setup\Patch\Data\EncryptApiSecrets;

/**
 * Secret encryption migration for EncryptApiSecrets.
 */
class EncryptApiSecretsTest extends TestCase
{
    #[Test]
    public function empty_value_rows_are_skipped(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->never())->method('encrypt');

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->never())->method('saveConfig');

        $patch = $this->createPatch($resourceConfig, $encryptor, [
            [
                'scope' => 'default',
                'scope_id' => 0,
                'path' => StoreConfigHelper::PATH['api_secret'],
                'value' => '',
            ],
        ]);

        $patch->apply();
    }

    #[Test]
    public function already_encrypted_value_rows_are_skipped(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->never())->method('encrypt');

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->never())->method('saveConfig');

        $patch = $this->createPatch($resourceConfig, $encryptor, [
            [
                'scope' => 'stores',
                'scope_id' => 1,
                'path' => StoreConfigHelper::PATH['api_secret'],
                'value' => '0:encryptedsecret',
            ],
        ]);

        $patch->apply();
    }

    #[Test]
    public function plain_value_is_encrypted_and_saved_with_row_scope(): void
    {
        $path = StoreConfigHelper::PATH['api_secret'];

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())
            ->method('encrypt')
            ->with('plain-secret')
            ->willReturn('0:encrypted');

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->once())
            ->method('saveConfig')
            ->with($path, '0:encrypted', 'stores', 5);

        $patch = $this->createPatch($resourceConfig, $encryptor, [
            ['scope' => 'stores', 'scope_id' => 5, 'path' => $path, 'value' => 'plain-secret'],
        ]);

        $patch->apply();
    }

    #[Test]
    public function rows_across_scopes_are_encrypted_and_saved_independently(): void
    {
        $path = StoreConfigHelper::PATH['api_secret'];
        $saved = [];

        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('encrypt')->willReturnCallback(
            fn (string $value): string => 'encrypted:' . $value
        );

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->exactly(2))
            ->method('saveConfig')
            ->willReturnCallback(
                function (string $savedPath, string $value, string $scope, int $scopeId) use (&$saved): void {
                    $saved[] = [$savedPath, $value, $scope, $scopeId];
                }
            );

        $patch = $this->createPatch($resourceConfig, $encryptor, [
            ['scope' => 'default', 'scope_id' => 0, 'path' => $path, 'value' => 'secret-default'],
            ['scope' => 'websites', 'scope_id' => 2, 'path' => $path, 'value' => 'secret-website'],
        ]);

        $patch->apply();

        $this->assertSame([
            [$path, 'encrypted:secret-default', 'default', 0],
            [$path, 'encrypted:secret-website', 'websites', 2],
        ], $saved);
    }

    #[Test]
    public function skipped_rows_do_not_stop_later_plain_rows(): void
    {
        $path = StoreConfigHelper::PATH['api_secret'];

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())
            ->method('encrypt')
            ->with('plain-secret')
            ->willReturn('0:encrypted');

        $resourceConfig = $this->createMock(Config::class);
        $resourceConfig->expects($this->once())
            ->method('saveConfig')
            ->with($path, '0:encrypted', 'stores', 5);

        $patch = $this->createPatch($resourceConfig, $encryptor, [
            ['scope' => 'default', 'scope_id' => 0, 'path' => $path, 'value' => ''],
            ['scope' => 'websites', 'scope_id' => 2, 'path' => $path, 'value' => '0:already-encrypted'],
            ['scope' => 'stores', 'scope_id' => 5, 'path' => $path, 'value' => 'plain-secret'],
        ]);

        $patch->apply();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function createPatch(Config $resourceConfig, EncryptorInterface $encryptor, array $rows): EncryptApiSecrets
    {
        $resourceConfig->method('getConnection')->willReturn($this->createConnection($rows));
        $resourceConfig->method('getTable')->willReturn('core_config_data');

        return new EncryptApiSecrets($resourceConfig, $encryptor);
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
