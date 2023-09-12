<?php

namespace Flekto\Postcode\Setup\Patch\Data;

use Flekto\Postcode\Helper\ApiClientHelper;
use Flekto\Postcode\Helper\StoreConfigHelper;
use Flekto\Postcode\Service\PostcodeApiClient;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class UpdateApiStatusConfig implements DataPatchInterface
{
    protected $_apiClientHelper;
    protected $_configWriter;
    protected $_storeConfigHelper;
    protected $_resourceConfig;

    /**
     * Constructor
     *
     * @access public
     * @param ApiClientHelper $apiClientHelper
     * @param WriterInterface $configWriter
     * @param StoreConfigHelper $storeConfigHelper
     * @param ConfigInterface $resourceConfig
     * @return void
     */
    public function __construct(
        ApiClientHelper $apiClientHelper,
        WriterInterface $configWriter,
        StoreConfigHelper $storeConfigHelper,
        ConfigInterface $resourceConfig
    ) {
        $this->_apiClientHelper = $apiClientHelper;
        $this->_configWriter = $configWriter;
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_resourceConfig = $resourceConfig;
    }

    /**
     * Get aliases (previous names) for the patch.
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Get array of patches that have to be executed prior to this.
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Apply patch.
     *
     * @return void
     */
    public function apply(): void
    {
        $this->_resourceConfig->getConnection()->startSetup();
        $connection = $this->_resourceConfig->getConnection();
        $select = $connection->select()
            ->from($this->_resourceConfig->getTable('core_config_data'), ['scope', 'scope_id', 'path', 'value'])
            ->where('path IN(?)', [StoreConfigHelper::PATH['api_key'], StoreConfigHelper::PATH['api_secret']]);

        $scopeValues = [];

        foreach ($connection->fetchAll($select) as $row) {
            $scopeValues[$row['scope']] ??= [];
            $scopeValues[$row['scope']][$row['scope_id']] ??= [];
            $scopeValues[$row['scope']][$row['scope_id']][$row['path']] = $row['value'];
        }

        foreach ($scopeValues as $scope => $scopeIdValues) {
            foreach ($scopeIdValues as $scopeId => $credentials) {

                if (empty($credentials[StoreConfigHelper::PATH['api_key']]) || empty($credentials[StoreConfigHelper::PATH['api_secret']])) {
                    continue;
                }

                try {

                    $client = $this->_storeConfigHelper->getApiClient();
                    $client->setCredentials($credentials[StoreConfigHelper::PATH['api_key']], $credentials[StoreConfigHelper::PATH['api_secret']]);
                    $accountInfo = $client->accountInfo();

                    $this->_resourceConfig->saveConfig(StoreConfigHelper::PATH['account_name'], $accountInfo['name'], $scope, $scopeId);

                    if ($accountInfo['hasAccess']) {

                        $this->_resourceConfig->saveConfig(StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE, $scope, $scopeId);
                        $countries = $client->internationalGetSupportedCountries();
                        $this->_resourceConfig->saveConfig(StoreConfigHelper::PATH['supported_countries'], json_encode($countries), $scope, $scopeId);

                    } else {

                        $this->_resourceConfig->saveConfig(StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE, $scope, $scopeId);
                    }

                } catch (\Flekto\Postcode\Service\Exception\AuthenticationException $e) {

                    $this->_resourceConfig->saveConfig(StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_INVALID_CREDENTIALS, $scope, $scopeId);
                }

                // Remove obsolete config paths.
                $this->_resourceConfig->deleteConfig('postcodenl_api/general/api_key_is_valid', $scope, $scopeId); // Replaced by postcodenl_api/status/account_status
                $this->_resourceConfig->deleteConfig('postcodenl_api/general/supported_countries', $scope, $scopeId); // Replaced by postcodenl_api/status/supported_countries
                $this->_resourceConfig->deleteConfig('postcodenl_api/general/account_name', $scope, $scopeId); // Replaced by postcodenl_api/status/account_name
            }
        }

        $this->_resourceConfig->getConnection()->endSetup();
    }
}
