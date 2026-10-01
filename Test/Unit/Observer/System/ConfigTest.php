<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Observer\System;

use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;
use Magento\Framework\App\Cache\Type\Config as CacheType;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Helper\ApiClientHelper;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Observer\System\Config;
use Psr\Log\LoggerInterface;

/**
 * Account config cleanup when stored credentials disappear.
 */
class ConfigTest extends TestCase
{
    #[Test]
    public function missing_credentials_delete_account_config_at_current_scope(): void
    {
        $deletes = [];

        $writer = $this->createMock(WriterInterface::class);
        $writer->expects($this->never())->method('save');
        $writer->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function ($path, $scope, $scopeId) use (&$deletes): void {
                $deletes[] = [$path, $scope, $scopeId];
            });

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('remove')->with('postcode-eu-status-stores-5');

        $cachePool = $this->createStub(CacheFrontendPool::class);
        $cachePool->method('get')->willReturn($cache);

        $cacheTypeList = $this->createMock(TypeListInterface::class);
        $cacheTypeList->expects($this->once())->method('cleanType')->with(CacheType::TYPE_IDENTIFIER);

        $storeConfigHelper = $this->createStub(StoreConfigHelper::class);
        $storeConfigHelper->method('getScopeFromRequest')->willReturn([ScopeInterface::SCOPE_STORES, 5]);
        $storeConfigHelper->method('hasCredentials')->willReturn(false);

        $config = new Config(
            $writer,
            $this->createStub(LoggerInterface::class),
            $cacheTypeList,
            $cachePool,
            $this->createStub(ApiClientHelper::class),
            $storeConfigHelper,
            $this->createStub(RequestInterface::class),
            $this->createStub(ManagerInterface::class)
        );

        $config->execute(new EventObserver(['changed_paths' => [StoreConfigHelper::PATH['api_key']]]));

        $this->assertSame([
            [StoreConfigHelper::PATH['account_name'], ScopeInterface::SCOPE_STORES, 5],
            [StoreConfigHelper::PATH['account_status'], ScopeInterface::SCOPE_STORES, 5],
        ], $deletes);
    }
}
