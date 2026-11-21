<?php

namespace PostcodeEu\AddressValidation\Observer\System;

use PostcodeEu\AddressValidation\Helper\ApiClientHelper;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

class Config implements ObserverInterface
{
    /** @var WriterInterface */
    protected $_configWriter;

    /** @var LoggerInterface */
    protected $_logger;

    /** @var ApiClientHelper */
    protected $_apiClientHelper;

    /** @var TypeListInterface */
    protected $_cacheTypeList;

    /** @var CacheFrontendPool */
    protected $_cacheFrontendPool;

    /** @var StoreConfigHelper */
    protected $_storeConfigHelper;

    /** @var RequestInterface */
    protected $_request;

    /** @var ManagerInterface */
    protected $_messageManager;

    /** @var string */
    protected $_scopeType;

    /** @var int */
    protected $_scopeId;

    /**
     * Constructor
     *
     * @access public
     * @param WriterInterface $configWriter
     * @param LoggerInterface $logger
     * @param TypeListInterface $cacheTypeList
     * @param CacheFrontendPool $cacheFrontendPool
     * @param ApiClientHelper $apiClientHelper
     * @param StoreConfigHelper $storeConfigHelper
     * @param RequestInterface $request
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        WriterInterface $configWriter,
        LoggerInterface $logger,
        TypeListInterface $cacheTypeList,
        CacheFrontendPool $cacheFrontendPool,
        ApiClientHelper $apiClientHelper,
        StoreConfigHelper $storeConfigHelper,
        RequestInterface $request,
        ManagerInterface $messageManager
    ) {
        $this->_configWriter = $configWriter;
        $this->_logger = $logger;
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheFrontendPool = $cacheFrontendPool;
        $this->_apiClientHelper = $apiClientHelper;
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_request = $request;
        $this->_messageManager = $messageManager;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        [$this->_scopeType, $this->_scopeId] = $this->_storeConfigHelper->getScopeFromRequest();

        if (empty($this->_request->getParam('refresh_api_data'))) {
            $hasChangedCredentials = count(array_intersect(
                $observer->getDataByKey('changed_paths') ?? [],
                [StoreConfigHelper::PATH['api_key'], StoreConfigHelper::PATH['api_secret']],
            )) > 0;

            if (!$hasChangedCredentials) {
                return; // Return if credentials didn't change.
            }

            // Credential(s) missing. Delete account info (status will fallback to "new" via default config).
            if (!$this->_storeConfigHelper->hasCredentials()) {
                $this->_deleteConfig('account_name');
                $this->_deleteConfig('account_status');
                $this->_purgeCachedData();
                return;
            }
        }

        $hasAccess = false;

        try {
            $client = $this->_apiClientHelper->getApiClient();
            $accountInfo = $client->accountInfo();
            $hasAccess = $accountInfo['hasAccess'] ?? false;

            $this->_saveConfig('account_name', $accountInfo['name']);

            if ($hasAccess) {
                $this->_saveConfig('account_status', ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE);
            } else {
                $this->_saveConfig('account_status', ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE);
            }
        } catch (\PostcodeEu\AddressValidation\Service\Exception\AuthenticationException $e) {
            $this->_saveConfig('account_status', ApiClientHelper::API_ACCOUNT_STATUS_INVALID_CREDENTIALS);
            $this->_deleteConfig('account_name');
        } catch (\PostcodeEu\AddressValidation\Service\Exception\ClientException $e) {
            $this->_deleteConfig('account_name');
            $this->_deleteConfig('account_status');
        } catch (\Throwable $e) {
            $this->_logger->error('Postcode.eu update account info FAILED.', ['exception' => $e]);
            $this->_messageManager->addErrorMessage(__('Failed to update account info: %1', $e->getMessage()));
        }

        if ($hasAccess) {
            try {
                $countries = $client->internationalGetSupportedCountries();
                $this->_saveConfig(
                    StoreConfigHelper::PATH['supported_countries'],
                    json_encode($countries, JSON_THROW_ON_ERROR),
                );
            } catch (\Throwable $e) {
                $this->_logger->error('Postcode.eu update countries FAILED.', ['exception' => $e]);
                $this->_messageManager->addErrorMessage(__('Failed to update countries: %1', $e->getMessage()));
            }
        }

        $this->_purgeCachedData(); // Clean cache to update status block.
    }

    /**
     * Clean config cache.
     */
    protected function _cleanConfigCache(): void
    {
        $this->_cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
    }

    /**
     * Purge cached data.
     */
    private function _purgeCachedData(): void
    {
        $this->_cleanConfigCache();
        $cache = $this->_cacheFrontendPool->get(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
        $cacheId = implode('-', [
            \PostcodeEu\AddressValidation\Block\System\Config\Status::CACHE_ID,
            $this->_scopeType,
            $this->_scopeId,
        ]);
        $cache->remove($cacheId);
    }

    /**
     * Save config value.
     *
     * @param string $path
     * @param string $value
     */
    private function _saveConfig(string $path, string $value): void
    {
        $this->_configWriter->save(StoreConfigHelper::PATH[$path] ?? $path, $value, $this->_scopeType, $this->_scopeId);
    }

    /**
     * Delete config value.
     *
     * @param string $path
     */
    private function _deleteConfig(string $path): void
    {
        $this->_configWriter->delete(StoreConfigHelper::PATH[$path] ?? $path, $this->_scopeType, $this->_scopeId);
    }
}
