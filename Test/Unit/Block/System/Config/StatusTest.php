<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Block\System\Config\Status;
use PostcodeEu\AddressValidation\Block\System\Config\Status\Exception as StatusException;
use PostcodeEu\AddressValidation\Helper\ApiClientHelper;
use PostcodeEu\AddressValidation\Helper\Data as DataHelper;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Model\UpdateNotification\UpdateNotifier;
use Psr\Log\LoggerInterface;

/**
 * Admin status UI branching and per-scope cache handling for Status.
 */
class StatusTest extends TestCase
{
    protected function setUp(): void
    {
        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnCallback(
            fn (string $type) => $this->createStub($type)
        );
        ObjectManager::setInstance($objectManager);
    }

    #[Test]
    #[DataProvider('descriptionProvider')]
    public function account_status_maps_to_description(string $status, string $expected): void
    {
        $block = $this->createBlock([
            'storeConfigHelper' => $this->createStoreConfigStub(['account_status' => $status]),
        ]);

        $this->assertSame($expected, (string) $block->getApiStatusDescription());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function descriptionProvider(): array
    {
        return [
            'new' => [ApiClientHelper::API_ACCOUNT_STATUS_NEW, 'new'],
            'active' => [ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE, 'active'],
            'invalid credentials' => [
                ApiClientHelper::API_ACCOUNT_STATUS_INVALID_CREDENTIALS,
                'invalid key and/or secret',
            ],
            'inactive' => [ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE, 'inactive'],
        ];
    }

    #[Test]
    #[DataProvider('hintProvider')]
    public function account_status_maps_to_hint(string $status, string $expected): void
    {
        $block = $this->createBlock([
            'storeConfigHelper' => $this->createStoreConfigStub(['account_status' => $status]),
        ]);

        $this->assertSame($expected, (string) $block->getApiStatusHint());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function hintProvider(): array
    {
        return [
            'new' => [
                ApiClientHelper::API_ACCOUNT_STATUS_NEW,
                'Enter your Postcode.eu API key and secret to connect.',
            ],
            'active' => [
                ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE,
                'The Postcode.eu API is successfully connected.',
            ],
            'invalid credentials' => [
                ApiClientHelper::API_ACCOUNT_STATUS_INVALID_CREDENTIALS,
                'The API key or secret is incorrect. Please check your credentials.',
            ],
            'inactive' => [
                ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE,
                'Your Postcode.eu subscription is inactive. Please log in to your account to resolve this.',
            ],
        ];
    }

    #[Test]
    #[DataProvider('statusAccessorProvider')]
    public function unknown_account_status_raises_status_exception(string $method): void
    {
        $block = $this->createBlock([
            'storeConfigHelper' => $this->createStoreConfigStub(['account_status' => 'unexpected']),
        ]);

        $this->expectException(StatusException::class);

        $block->$method();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statusAccessorProvider(): array
    {
        return [
            'description' => ['getApiStatusDescription'],
            'hint' => ['getApiStatusHint'],
        ];
    }

    #[Test]
    public function config_exposes_configured_status_values(): void
    {
        $block = $this->createBlock([
            'storeConfigHelper' => $this->createStoreConfigStub([
                'account_status' => ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE,
            ]),
        ]);

        $config = $block->getConfig();

        $this->assertSame(true, $config['enabled']);
        $this->assertSame('1.2.3', $config['module_version']);
        $this->assertSame(['Netherlands'], $config['supported_countries']);
        $this->assertSame('Postcode.eu', $config['account_name']);
        $this->assertSame(ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE, $config['account_status']);
        $this->assertSame(true, $config['has_credentials']);
    }

    #[Test]
    #[DataProvider('statusActiveProvider')]
    public function status_active_only_for_active_account(?string $status, bool $expected): void
    {
        $block = $this->createBlock([
            'storeConfigHelper' => $this->createStoreConfigStub(['account_status' => $status]),
        ]);

        $this->assertSame($expected, $block->isStatusActive());
    }

    /**
     * @return array<string, array{string|null, bool}>
     */
    public static function statusActiveProvider(): array
    {
        return [
            'active' => [ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE, true],
            'new' => [ApiClientHelper::API_ACCOUNT_STATUS_NEW, false],
            'invalid credentials' => [ApiClientHelper::API_ACCOUNT_STATUS_INVALID_CREDENTIALS, false],
            'inactive' => [ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE, false],
            'missing' => [null, false],
        ];
    }

    #[Test]
    public function api_availability_follows_api_client(): void
    {
        $apiClient = $this->createMock(ApiClientHelper::class);
        $apiClient->expects($this->once())->method('isApiAvailable')->willReturn(true);

        $block = $this->createBlock(['apiClientHelper' => $apiClient]);

        $this->assertTrue($block->isApiAvailable());
    }

    #[Test]
    public function cache_miss_fetches_and_stores_account_and_module_info(): void
    {
        $accountInfo = ['account_name' => 'Postcode.eu'];
        $moduleInfo = ['version' => '1.2.3', 'latest_version' => '1.2.3', 'has_update' => false];
        $cacheId = 'postcode-eu-status-stores-5';

        $apiClient = $this->createMock(ApiClientHelper::class);
        $apiClient->expects($this->once())->method('getAccountInfo')->willReturn($accountInfo);

        $dataHelper = $this->createStub(DataHelper::class);
        $dataHelper->method('getModuleInfo')->willReturn($moduleInfo);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('load')->with($cacheId)->willReturn(false);
        $cache->expects($this->once())
            ->method('save')
            ->with(
                json_encode(['accountInfo' => $accountInfo, 'moduleInfo' => $moduleInfo]),
                $cacheId,
                [],
                Status::CACHE_LIFETIME_SECONDS
            )
            ->willReturn(true);

        $block = $this->createRenderingBlock([
            'storeConfigHelper' => $this->createStoreConfigStub([
                'scope' => [ScopeInterface::SCOPE_STORES, 5],
                'account_status' => ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE,
            ]),
            'apiClientHelper' => $apiClient,
            'cacheFrontendPool' => $this->createCachePool($cache),
            'dataHelper' => $dataHelper,
        ]);

        $block->render($this->createStub(AbstractElement::class));

        $this->assertSame($accountInfo, $block->getAccountInfo());
        $this->assertSame($moduleInfo, $block->getModuleInfo());
    }

    #[Test]
    public function unencodable_payload_is_rendered_but_not_cached(): void
    {
        $accountInfo = ['account_name' => "\xB1\x31"];
        $moduleInfo = ['version' => '1.2.3'];

        $apiClient = $this->createMock(ApiClientHelper::class);
        $apiClient->expects($this->once())->method('getAccountInfo')->willReturn($accountInfo);

        $dataHelper = $this->createStub(DataHelper::class);
        $dataHelper->method('getModuleInfo')->willReturn($moduleInfo);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('load')->willReturn(false);
        $cache->expects($this->never())->method('save');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $block = $this->createRenderingBlock([
            'storeConfigHelper' => $this->createStoreConfigStub([
                'account_status' => ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE,
            ]),
            'apiClientHelper' => $apiClient,
            'cacheFrontendPool' => $this->createCachePool($cache),
            'dataHelper' => $dataHelper,
            'logger' => $logger,
        ]);

        $block->render($this->createStub(AbstractElement::class));

        $this->assertSame($accountInfo, $block->getAccountInfo());
        $this->assertSame($moduleInfo, $block->getModuleInfo());
    }

    #[Test]
    #[DataProvider('cacheIdProvider')]
    public function cache_id_includes_request_scope(string $scopeType, int $scopeId, string $expectedId): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('load')->with($expectedId)->willReturn(false);
        $cache->expects($this->once())
            ->method('save')
            ->with($this->anything(), $expectedId, [], Status::CACHE_LIFETIME_SECONDS)
            ->willReturn(true);

        $block = $this->createRenderingBlock([
            'storeConfigHelper' => $this->createStoreConfigStub([
                'scope' => [$scopeType, $scopeId],
                'account_status' => ApiClientHelper::API_ACCOUNT_STATUS_INVALID_CREDENTIALS,
            ]),
            'cacheFrontendPool' => $this->createCachePool($cache),
        ]);

        $block->render($this->createStub(AbstractElement::class));
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function cacheIdProvider(): array
    {
        return [
            'store scope' => [ScopeInterface::SCOPE_STORES, 5, 'postcode-eu-status-stores-5'],
            'website scope' => [ScopeInterface::SCOPE_WEBSITES, 2, 'postcode-eu-status-websites-2'],
            'default scope' => ['default', 0, 'postcode-eu-status-default-0'],
        ];
    }

    #[Test]
    public function cache_hit_uses_stored_payload_without_refetching(): void
    {
        $cachedData = [
            'accountInfo' => ['account_name' => 'Cached'],
            'moduleInfo' => ['version' => '1.2.3', 'has_update' => false],
        ];

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('load')->with('postcode-eu-status-stores-5')
            ->willReturn(json_encode($cachedData));
        $cache->expects($this->never())->method('save');

        $apiClient = $this->createMock(ApiClientHelper::class);
        $apiClient->expects($this->never())->method('getAccountInfo');

        $dataHelper = $this->createMock(DataHelper::class);
        $dataHelper->expects($this->never())->method('getModuleInfo');

        $block = $this->createRenderingBlock([
            'storeConfigHelper' => $this->createStoreConfigStub([
                'scope' => [ScopeInterface::SCOPE_STORES, 5],
            ]),
            'apiClientHelper' => $apiClient,
            'cacheFrontendPool' => $this->createCachePool($cache),
            'dataHelper' => $dataHelper,
        ]);

        $block->render($this->createStub(AbstractElement::class));

        $this->assertSame(['account_name' => 'Cached'], $block->getAccountInfo());
        $this->assertSame(['version' => '1.2.3', 'has_update' => false], $block->getModuleInfo());
    }

    #[Test]
    #[DataProvider('malformedCachedDataProvider')]
    public function malformed_cache_hit_degrades_to_empty_info(string $cachedData): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn($cachedData);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $block = $this->createRenderingBlock([
            'cacheFrontendPool' => $this->createCachePool($cache),
            'logger' => $logger,
        ]);

        $block->render($this->createStub(AbstractElement::class));

        $this->assertSame([], $block->getAccountInfo());
        $this->assertSame([], $block->getModuleInfo());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedCachedDataProvider(): array
    {
        return [
            'invalid json' => ['not-an-array'],
            'scalar payload' => ['42'],
            'missing keys' => ['{"unexpected":true}'],
            'wrong key types' => ['{"accountInfo":"nope","moduleInfo":42}'],
        ];
    }

    #[Test]
    #[DataProvider('inactiveStatusProvider')]
    public function account_info_left_empty_unless_status_active(?string $status): void
    {
        $apiClient = $this->createMock(ApiClientHelper::class);
        $apiClient->expects($this->never())->method('getAccountInfo');

        $block = $this->createRenderingBlock([
            'storeConfigHelper' => $this->createStoreConfigStub(['account_status' => $status]),
            'apiClientHelper' => $apiClient,
            'cacheFrontendPool' => $this->createCachePool($this->createCacheStub()),
        ]);

        $block->render($this->createStub(AbstractElement::class));

        $this->assertSame([], $block->getAccountInfo());
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function inactiveStatusProvider(): array
    {
        return [
            'new' => [ApiClientHelper::API_ACCOUNT_STATUS_NEW],
            'invalid credentials' => [ApiClientHelper::API_ACCOUNT_STATUS_INVALID_CREDENTIALS],
            'inactive' => [ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE],
            'missing' => [null],
        ];
    }

    #[Test]
    public function update_notification_sent_when_cached_module_flags_update(): void
    {
        $updateNotifier = $this->createMock(UpdateNotifier::class);
        $updateNotifier->expects($this->once())->method('notifyVersion')->with('1.3.0');

        $block = $this->createRenderingBlockWithCacheData(
            ['accountInfo' => [], 'moduleInfo' => ['has_update' => true, 'latest_version' => '1.3.0']],
            ['updateNotifier' => $updateNotifier]
        );

        $block->render($this->createStub(AbstractElement::class));
    }

    #[Test]
    #[DataProvider('noUpdateProvider')]
    public function update_notification_skipped_without_cached_update(array $moduleInfo): void
    {
        $updateNotifier = $this->createMock(UpdateNotifier::class);
        $updateNotifier->expects($this->never())->method('notifyVersion');

        $block = $this->createRenderingBlockWithCacheData(
            ['accountInfo' => [], 'moduleInfo' => $moduleInfo],
            ['updateNotifier' => $updateNotifier]
        );

        $block->render($this->createStub(AbstractElement::class));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function noUpdateProvider(): array
    {
        return [
            'update flag false' => [['has_update' => false, 'latest_version' => '1.2.3']],
            'update flag absent' => [['version' => '1.2.3']],
            'update flag true without latest version' => [['has_update' => true]],
        ];
    }

    /**
     * @param array<string, object> $overrides
     * @return array<int, mixed>
     */
    private function constructorArgs(array $overrides): array
    {
        $context = $overrides['context'] ?? $this->createStub(Context::class);
        $context->method('getLogger')->willReturn(
            $overrides['logger'] ?? $this->createStub(LoggerInterface::class)
        );

        return [
            $context,
            $overrides['storeConfigHelper'] ?? $this->createStoreConfigStub(),
            $overrides['apiClientHelper'] ?? $this->createStub(ApiClientHelper::class),
            $overrides['resourceConfig'] ?? $this->createStub(ConfigInterface::class),
            $overrides['cacheTypeList'] ?? $this->createStub(TypeListInterface::class),
            $overrides['cacheFrontendPool'] ?? $this->createCachePool($this->createCacheStub()),
            $overrides['dataHelper'] ?? $this->createStub(DataHelper::class),
            $overrides['updateNotifier'] ?? $this->createStub(UpdateNotifier::class),
        ];
    }

    /**
     * @param array<string, object> $overrides
     */
    private function createBlock(array $overrides = []): Status
    {
        return new Status(...$this->constructorArgs($overrides));
    }

    /**
     * @param array<string, object> $overrides
     */
    private function createRenderingBlock(array $overrides = []): Status
    {
        $args = $this->constructorArgs($overrides);

        return new class (...$args) extends Status {
            public function toHtml(): string
            {
                return '';
            }
        };
    }

    /**
     * @param array<string, mixed> $cachedData
     * @param array<string, object> $overrides
     */
    private function createRenderingBlockWithCacheData(array $cachedData, array $overrides = []): Status
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(json_encode($cachedData));

        return $this->createRenderingBlock($overrides + [
            'cacheFrontendPool' => $this->createCachePool($cache),
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createStoreConfigStub(array $config = []): StoreConfigHelper
    {
        $accountStatus = array_key_exists('account_status', $config)
            ? $config['account_status']
            : ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE;

        $values = [
            'account_name' => $config['account_name'] ?? 'Postcode.eu',
            'account_status' => $accountStatus,
        ];

        $helper = $this->createStub(StoreConfigHelper::class);
        $helper->method('isEnabled')->willReturn($config['enabled'] ?? true);
        $helper->method('getModuleVersion')->willReturn($config['module_version'] ?? '1.2.3');
        $helper->method('getSupportedCountryNames')->willReturn($config['supported_countries'] ?? ['Netherlands']);
        $helper->method('hasCredentials')->willReturn($config['has_credentials'] ?? true);
        $helper->method('getScopeFromRequest')->willReturn($config['scope'] ?? [ScopeInterface::SCOPE_STORES, 1]);
        $helper->method('getValue')->willReturnCallback(
            fn (string $path) => $values[$path] ?? null
        );

        return $helper;
    }

    private function createCachePool(CacheInterface $cache): CacheFrontendPool
    {
        $pool = $this->createStub(CacheFrontendPool::class);
        $pool->method('get')->willReturn($cache);

        return $pool;
    }

    private function createCacheStub(): CacheInterface
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->method('save')->willReturn(true);

        return $cache;
    }
}
