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
use Psr\Log\LoggerInterface;

class Config implements ObserverInterface
{
    protected $_configWriter;
    protected $_logger;
    protected $_apiClientHelper;
    protected $_cacheTypeList;
    protected $_cacheFrontendPool;
    protected $_storeConfigHelper;
    protected $_request;

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
     * @return void
     */
    public function __construct(
        WriterInterface $configWriter,
        LoggerInterface $logger,
        TypeListInterface $cacheTypeList,
        CacheFrontendPool $cacheFrontendPool,
        ApiClientHelper $apiClientHelper,
        StoreConfigHelper $storeConfigHelper,
        RequestInterface $request
    ) {
        $this->_configWriter = $configWriter;
        $this->_logger = $logger;
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheFrontendPool = $cacheFrontendPool;
        $this->_apiClientHelper = $apiClientHelper;
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_request = $request;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        if (empty($this->_request->getParam('refresh_api_data'))) {

            // Return if credentials didn't change.
            if (empty(array_intersect($observer->getDataByKey('changed_paths'), [StoreConfigHelper::PATH['api_key'], StoreConfigHelper::PATH['api_secret']]))) {
                return;
            }

            // Credential(s) missing. Delete account info (status will fallback to "new" via default config).
            if (!$this->_storeConfigHelper->hasCredentials()) {

                $this->_configWriter->delete(StoreConfigHelper::PATH['account_name']);
                $this->_configWriter->delete(StoreConfigHelper::PATH['account_status']);
                $this->_purgeCachedData();

                return;
            }
        }

        try {

            $client = $this->_apiClientHelper->getApiClient();
            $accountInfo = $client->accountInfo();

            $this->_configWriter->save(StoreConfigHelper::PATH['account_name'], $accountInfo['name']);

            if ($accountInfo['hasAccess']) {
                $this->_configWriter->save(StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE);
            } else {
                $this->_configWriter->save(StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE);
            }

        } catch (\PostcodeEu\AddressValidation\Service\Exception\AuthenticationException $e) {

            $this->_configWriter->save(StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_INVALID_CREDENTIALS);
            $this->_configWriter->delete(StoreConfigHelper::PATH['account_name']);

        } catch (\PostcodeEu\AddressValidation\Service\Exception\ClientException $e) {

            $this->_configWriter->delete(StoreConfigHelper::PATH['account_name']);
            $this->_configWriter->delete(StoreConfigHelper::PATH['account_status']);

        } catch (\Exception $e) {

            $this->_logger->error(__('Postcode.eu update account info FAILED: ') . json_encode($e->getMessage()));
            throw $e; // Shows exception message in error message on page.
        }

        if (isset($accountInfo) && $accountInfo['hasAccess']) {

            try {
                $countries = $client->internationalGetSupportedCountries();
                $this->_configWriter->save(StoreConfigHelper::PATH['supported_countries'], json_encode($countries));

            } catch (\Exception $e) {

                $this->_logger->error(__('Postcode.eu update countries FAILED: ') . json_encode($e->getMessage()));
                throw $e;
            }
        }

        $this->_purgeCachedData(); // Clean cache to update status block.
    }

    /**
     * Clean config cache.
     *
     * @return void
     */
    protected function _cleanConfigCache(): void
    {
        $this->_cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
    }

    /**
     * Purge cached data.
     *
     * @return void
     */
    private function _purgeCachedData(): void
    {
        $this->_cleanConfigCache();
        $cache = $this->_cacheFrontendPool->get(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
        $cache->remove(\PostcodeEu\AddressValidation\Block\System\Config\Status::CACHE_ID);
    }
}
