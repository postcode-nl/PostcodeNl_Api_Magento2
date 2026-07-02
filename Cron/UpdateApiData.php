<?php

namespace PostcodeEu\AddressValidation\Cron;

use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PostcodeEu\AddressValidation\Helper\ApiClientHelper;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use Psr\Log\LoggerInterface;

class UpdateApiData
{
    /** @var LoggerInterface */
    protected $_logger;
    /** @var ApiClientHelper */
    protected $_apiClientHelper;
    /** @var WriterInterface */
    protected $_configWriter;
    /** @var StoreConfigHelper */
    protected $_storeConfigHelper;
    /** @var StoreManagerInterface */
    protected $_storeManager;
    /** @var CollectionFactory */
    protected $_configCollectionFactory;
    /** @var EncryptorInterface */
    protected $_encryptor;
    /** @var TypeListInterface */
    protected $_cacheTypeList;
    /** @var bool */
    protected $_hasChanges = false;
    /** @var array */
    protected $_existingConfig = [];

    /**
     * Constructor
     *
     * @access public
     * @param LoggerInterface $logger
     * @param ApiClientHelper $apiClientHelper
     * @param WriterInterface $configWriter
     * @param StoreConfigHelper $storeConfigHelper
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $configCollectionFactory
     * @param EncryptorInterface $encryptor
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        LoggerInterface $logger,
        ApiClientHelper $apiClientHelper,
        WriterInterface $configWriter,
        StoreConfigHelper $storeConfigHelper,
        StoreManagerInterface $storeManager,
        CollectionFactory $configCollectionFactory,
        EncryptorInterface $encryptor,
        TypeListInterface $cacheTypeList
    ) {
        $this->_logger = $logger;
        $this->_apiClientHelper = $apiClientHelper;
        $this->_configWriter = $configWriter;
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_storeManager = $storeManager;
        $this->_configCollectionFactory = $configCollectionFactory;
        $this->_encryptor = $encryptor;
        $this->_cacheTypeList = $cacheTypeList;
    }

    /**
     * Update API data on each scope.
     */
    public function execute(): void
    {
        $this->_hasChanges = false;
        $this->_preloadConfig();
        $scopesToProcess = [['type' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 'id' => 0, 'name' => 'Default']];
        $groupedScopes = [];

        foreach ($this->_storeManager->getWebsites() as $website) {
            $scopesToProcess[] = [
                'type' => ScopeInterface::SCOPE_WEBSITES,
                'id' => (int)$website->getId(),
                'name' => $website->getName(),
            ];
        }

        foreach ($this->_storeManager->getStores() as $store) {
            $scopesToProcess[] = [
                'type' => ScopeInterface::SCOPE_STORES,
                'id' => (int)$store->getId(),
                'name' => $store->getName(),
            ];
        }

        foreach ($scopesToProcess as $scope) {
            [$key, $secret] = $this->_getCredentials($scope['type'], $scope['id']);

            if (!isset($key, $secret)) {
                continue;
            }

            $hash = hash('sha256', $key . ':' . $secret);
            $groupedScopes[$hash] ??= ['key' => $key, 'secret' => $secret, 'scopes' => []];
            $groupedScopes[$hash]['scopes'][] = $scope;

        }

        foreach ($groupedScopes as $group) {
            $this->_updateApiDataForGroup($group['key'], $group['secret'], $group['scopes']);
        }

        if ($this->_hasChanges) {
            $this->_cacheTypeList->cleanType('config');
        }
    }

    /**
     * Update Postcode.eu API account data for a group of scopes sharing the same credentials.
     *
     * @param string $key
     * @param string $secret
     * @param array $scopes
     */
    private function _updateApiDataForGroup(string $key, string $secret, array $scopes): void
    {
        $scopeNames = array_column($scopes, 'name');
        $scopeNamesJoined = implode(', ', $scopeNames);

        $this->_logger->info(sprintf(
            'Postcode.eu API data update start for scope(s): "%s".',
            $scopeNamesJoined
        ));

        $client = $this->_apiClientHelper->getApiClient();
        $client->setCredentials($key, $secret);

        try {
            $accountInfo = $client->accountInfo();
            $accountName = $accountInfo['name'] ?? '[UNKNOWN]';
            $hasAccess = $accountInfo['hasAccess'] ?? false;
            $accountStatus = $hasAccess
                ? ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE
                : ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE;
            $countriesJson = null;

            if ($hasAccess) {
                $countries = $client->internationalGetSupportedCountries();
                $countriesJson = json_encode($countries, JSON_THROW_ON_ERROR);
                $this->_logger->info(sprintf(
                    'Postcode.eu API countries updated for scope(s) "%s": %s',
                    $scopeNamesJoined,
                    implode(', ', array_column($countries, 'iso2'))
                ));
            }

            foreach ($scopes as $scope) {
                $this->_saveIfChanged(StoreConfigHelper::PATH['account_name'], $accountName, $scope['type'], $scope['id']);
                $this->_saveIfChanged(StoreConfigHelper::PATH['account_status'], $accountStatus, $scope['type'], $scope['id']);
                $this->_saveIfChanged(StoreConfigHelper::PATH['supported_countries'], $countriesJson, $scope['type'], $scope['id']);
            }

            $this->_logger->info(sprintf(
                'Postcode.eu API account info updated for scope(s) "%s": name "%s", status "%s".',
                $scopeNamesJoined,
                $accountName,
                $accountStatus
            ));
        } catch (\Throwable $e) {
            $this->_logger->error(sprintf(
                'Postcode.eu API data update FAILED for scope(s) "%s": %s',
                $scopeNamesJoined,
                $e->getMessage()
            ), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Pre-load all relevant config values into memory.
     */
    private function _preloadConfig(): void
    {
        $collection = $this->_configCollectionFactory->create();
        $collection->addFieldToFilter('path', ['in' => [
            StoreConfigHelper::PATH['api_key'],
            StoreConfigHelper::PATH['api_secret'],
            StoreConfigHelper::PATH['account_name'],
            StoreConfigHelper::PATH['account_status'],
            StoreConfigHelper::PATH['supported_countries']
        ]]);

        $this->_existingConfig = [];

        foreach ($collection as $item) {
            $this->_existingConfig[$item->getScope()][$item->getScopeId()][$item->getPath()] = $item->getValue();
        }
    }

    /**
     * Save config value only if it has changed.
     *
     * @param string $path
     * @param mixed $value
     * @param string $scopeType
     * @param int $scopeId
     */
    private function _saveIfChanged(string $path, $value, string $scopeType, int $scopeId): void
    {
        $currentValue = $this->_existingConfig[$scopeType][$scopeId][$path] ?? null;
        $normalizedValue = $value === null ? null : (string)$value;

        if ($currentValue !== $normalizedValue) {
            if ($value === null) {
                $this->_configWriter->delete($path, $scopeType, $scopeId);
            } else {
                $this->_configWriter->save($path, $value, $scopeType, $scopeId);
            }

            $this->_existingConfig[$scopeType][$scopeId][$path] = $normalizedValue;
            $this->_hasChanges = true;
        }
    }

    /**
     * Get credentials explicitly defined at the given scope.
     *
     * Does NOT fall back to parent scopes.
     *
     * @param string $scopeType
     * @param int $scopeId
     * @return array [key, secret]
     */
    private function _getCredentials(string $scopeType, int $scopeId): array
    {
        $key = $this->_existingConfig[$scopeType][$scopeId][StoreConfigHelper::PATH['api_key']] ?? null;
        $secretValue = $this->_existingConfig[$scopeType][$scopeId][StoreConfigHelper::PATH['api_secret']] ?? null;
        $secret = $secretValue === null ? null : $this->_encryptor->decrypt($secretValue);

        return [$key, $secret];
    }
}
