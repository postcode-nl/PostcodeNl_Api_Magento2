<?php

namespace PostcodeEu\AddressValidation\Cron;

use PostcodeEu\AddressValidation\Helper\ApiClientHelper;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Psr\Log\LoggerInterface;

class UpdateApiData
{
    protected $_logger;
    protected $_apiClientHelper;
    protected $_configWriter;
    protected $_storeConfigHelper;

    /**
     * Constructor
     *
     * @access public
     * @param LoggerInterface $logger
     * @param ApiClientHelper $apiClientHelper
     * @param WriterInterface $configWriter
     * @param StoreConfigHelper $storeConfigHelper
     * @return void
     */
    public function __construct(
        LoggerInterface $logger,
        ApiClientHelper $apiClientHelper,
        WriterInterface $configWriter,
        StoreConfigHelper $storeConfigHelper
    ) {
        $this->_logger = $logger;
        $this->_apiClientHelper = $apiClientHelper;
        $this->_configWriter = $configWriter;
        $this->_storeConfigHelper = $storeConfigHelper;
    }

    /**
     * Update Postcode.eu API account data.
     */
    public function execute(): void
    {
        if (!$this->_storeConfigHelper->hasCredentials()) {
            return; // No credentials so nothing to do here.
        }

        $this->_logger->info(__('Postcode.eu API data update start'));

        try {

            $client = $this->_apiClientHelper->getApiClient();
            $accountInfo = $client->accountInfo();
            $this->_configWriter->save(StoreConfigHelper::PATH['account_name'], $accountInfo['name']);

            if ($accountInfo['hasAccess']) {

                $this->_configWriter->save(StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE);
                $countries = $client->internationalGetSupportedCountries();
                $this->_configWriter->save(StoreConfigHelper::PATH['supported_countries'], json_encode($countries));
                $iso2Codes = array_column($countries, 'iso2');
                $this->_logger->info(__('Postcode.eu API countries updated: ') . implode(', ', $iso2Codes));

            } else {

                $this->_configWriter->save(StoreConfigHelper::PATH['account_status'], ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE);
            }

            $this->_logger->info(__('Postcode.eu API account info updated: ') . 'name: ' . $accountInfo['name'] . ', status: ' . ($accountInfo['hasAccess'] ? 'active' : 'inactive'));

        } catch (\Exception $e) {

            $this->_logger->error(__('Postcode.eu API data update FAILED: ') . json_encode($e->getMessage()));
            return;
        }

        $this->_logger->info(__('Postcode.eu API data update complete'));
    }
}
