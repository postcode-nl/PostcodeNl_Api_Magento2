<?php

namespace Flekto\Postcode\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Flekto\Postcode\Helper\PostcodeApiClient;
use Magento\Developer\Helper\Data;
use Flekto\Postcode\Helper\CountryCodeConvertorHelper;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Framework\Stdlib\DateTime;
use Flekto\Postcode\Helper\Exception\NotFoundException;
use Magento\Store\Model\StoreManagerInterface;


class ApiClientHelper extends AbstractHelper
{
    protected $modules = null;

    protected $moduleList;
    protected $developerHelper;
    protected $request;
    protected $response;


    /**
     * __construct function.
     *
     * @access public
     * @param ModuleListInterface $moduleList
     * @param Data $developerHelper
     * @param Context $context
     * @param Request $request
     * @param Response $response
     * @param StoreManagerInterface $storeManager
     * @return void
     */
    public function __construct(ModuleListInterface $moduleList, Data $developerHelper, Context $context, Request $request, Response $response, StoreManagerInterface $storeManager) {
        $this->moduleList = $moduleList;
        $this->developerHelper = $developerHelper;
        $this->request = $request;
        $this->response = $response;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }


    /**
     * getJsinit function.
     *
     * @access public
     * @return array
     */
    public function getJsinit(): array
    {
        if (!$this->getStoreConfig('postcodenl_api/general/enabled')) {
            return ['enabled' => false];
        }

        $settings = [
            'enabled' => (bool)$this->getStoreConfig('postcodenl_api/general/enabled'),
            'supported_countries' => json_encode($this->formatSupportedCountriesJs($this->getStoreConfig('postcodenl_api/general/supported_countries'))),
            'nl_input_behavior' => (!empty($this->getStoreConfig('postcodenl_api/general/nl_input_behavior')) ? $this->getStoreConfig('postcodenl_api/general/nl_input_behavior') : 'zip_house'),
            'show_hide_address_fields' => (!empty($this->getStoreConfig('postcodenl_api/general/show_hide_address_fields')) ? $this->getStoreConfig('postcodenl_api/general/show_hide_address_fields') : 'show'),
            'base_url' => $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB),
            'debug' => $this->isDebugging(),
            'fixedCountry' => $this->_getFixedCountry(),
        ];

        return $settings;
    }


    /**
     * formatSupportedCountriesJs function.
     *
     * @access public
     * @param Mixed $countries (default: Array)
     * @return String
     */
    public function formatSupportedCountriesJs($countries=[])
    {
        if (empty($countries)) return [];

        $countries = explode(', ', $countries);
        $countriesReturn = [];
        if (!empty($countries)) {
            foreach ($countries as $country) {
                $countriesReturn[] = CountryCodeConvertorHelper::alpha3ToAlpha2($country);
            }
        }

        return $countriesReturn;
    }


    /**
     * _prepareApiClient function.
     *
     * @access private
     * @return PostcodeApiClient
     */
    private function _prepareApiClient(): PostcodeApiClient
    {
        $isApiReady = $this->isPostCodeApiReady();
        if ($isApiReady !== true) {
            return $isApiReady;
        }

        $client = new PostcodeApiClient($this->_getKey(), $this->_getSecret());
        return $client;
    }


    /**
     * getAddressAutocomplete function.
     *
     * @access public
     * @param string $context
     * @param string $term
     * @return array
     */
    public function getAddressAutocomplete(string $context, string $term): array
    {
        $context = CountryCodeConvertorHelper::alpha2ToAlpha3($context);
        $client = $this->_prepareApiClient();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $locale = $objectManager->get('Magento\Framework\Locale\Resolver')->getLocale();

        // API requires format 'nl-NL'
        $locale = str_replace('_', '-', $locale);

        try {

            $sessionStr = $this->request->getHeader($client::SESSION_HEADER_KEY);
            if (empty($sessionStr)) {
                $sessionStr = $this->generateSessionString();
            }

            $response = $client->internationalAutocomplete($context, $term, $sessionStr, $locale);

            return $this->_prepareResponse($response, $client);

        } catch (\Exception $e) {
            return $this->_handleClientException($e);
        }
    }


    /**
     * getAddressDetails function.
     *
     * @access public
     * @param string $context
     * @param string $dispatchCountry
     * @return array
     */
    public function getAddressDetails(string $context, string $dispatchCountry = ''): array
    {
        if (strlen($dispatchCountry) > 2) {
            $dispatchCountry = CountryCodeConvertorHelper::alpha2ToAlpha3($dispatchCountry);
        }

        $client = $this->_prepareApiClient();

        try {

            $sessionStr = $this->request->getHeader($client::SESSION_HEADER_KEY);
            if (empty($sessionStr)) {
                $sessionStr = $this->generateSessionString();
            }

            $response = $client->internationalGetDetails($context, $sessionStr);
            return $this->_prepareResponse($response, $client);

        } catch (\Exception $e) {
            return $this->_handleClientException($e);
        }
    }


    /**
     * getNlAddress function.
     *
     * @access public
     * @param string $zipCode
     * @param string $houseNumber
     * @return array
     */
    public function getNlAddress(string $zipCode, string $houseNumber): array
    {
        $client = $this->_prepareApiClient();
        $address = null;

        preg_match('/^(\d{1,5})(\D.*)?$/i', $houseNumber, $matches);
        $houseNumber = isset($matches[1]) ? (int)$matches[1] : null;
        $houseNumberAddition = isset($matches[2]) ? trim($matches[2]) : null;

        if (is_null($houseNumber)) {
            return ['error' => true, 'message_details' => __('Invalid house number.')];
        }

        try {
            $address = $client->dutchAddressByPostcode($zipCode, $houseNumber, $houseNumberAddition);
            $address = $this->_prepareResponse($address, $client);
            $status = 'valid';

            if (
                (isset($address['parsedHouseNumberAddition']) && strcasecmp($address['parsedHouseNumberAddition'], $address['houseNumberAddition'] ?? '') != 0)
                ||
                (!isset($address['parsedHouseNumberAddition']) && strcasecmp($address['houseNumberAddition'] ?? '', $houseNumberAddition ?? '') != 0)
                ||
                (!empty($address['houseNumberAdditions']) && is_null($address['houseNumberAddition']))
            ) {
                $status = 'houseNumberAdditionIncorrect';
            }
        } catch (NotFoundException $e) {
            $status = 'notFound';
        } catch (\Exception $e) {
            return $this->_handleClientException($e);
        }

        $formattedHouseNumberAdditions = [];

        foreach ($address['houseNumberAdditions'] ?? [] as $addition) {
            $houseNumberWithAddition = rtrim($address['houseNumber'] . ' ' . $addition);
            $formattedHouseNumberAdditions[] = [
                'label' => $houseNumberWithAddition,
                'value' => $houseNumberWithAddition,
                'houseNumberAddition' => $addition,
            ];
        }

        $address['houseNumberAdditions'] = $formattedHouseNumberAdditions;

        $out = ['address' => $address, 'status' => $status];

        if ($this->isDebugging()) {
            $out['debug'] = [
                'parsedHouseNumber' => $houseNumber,
                'parsedHouseNumberAddition' => $houseNumberAddition,
            ];
        }

        return $out;
    }


    /**
     * _generateSessionString function.
     *
     * @access private
     * @return string
     */
    private function _generateSessionString(): string
    {
        return bin2hex(random_bytes(8));
    }


    /**
     * _handleClientException function.
     *
     * @access private
     * @param mixed $exception
     * @return array
     */
    private function _handleClientException(\Exception $exception): array
    {
        $response['error'] = true;

        // only in this case we actually pass error
        // to front-end without debug option needed
        if ($exception instanceof NotFoundException) {
            $response['message_details'] = __("Combination not found.");
        }

        if (!$this->isDebugging()) {
            if (empty($response['message_details'])) {
                $response['message_details'] = __("Something went wrong. Please try again.");
            }

            return $response;
        }

        $exceptionClass = get_class($exception);
        $response['message'] = sprintf(__('Exception %s occurred'), $exceptionClass).$exception->getTraceAsString();

        $response['message_details'] = __($exception->getMessage());
        $response['magentoDebugInfo'] = $this->_getDebugInfo();

        return $response;
    }


    /**
     * _prepareResponse function.
     *
     * @access private
     * @param mixed $apiResult
     * @param PostcodeApiClient $client
     * @return array
     */
    private function _prepareResponse(array $apiResult, PostcodeApiClient $client): array
    {
        // set Cache-Control header from API response
        $clientResponseHeaders = $client->getApiCallResponseHeaders();
        if (!empty($clientResponseHeaders) && isset($clientResponseHeaders['cache-control']) && !empty($clientResponseHeaders['cache-control'])) {
            $this->response->setHeader('Cache-control', $clientResponseHeaders['cache-control'][0], true);
            $this->response->setHeader('Pragma', 'cache', true);

            preg_match("#max-age=(.*?)$#sim", $clientResponseHeaders['cache-control'][0], $secondsToLive);
            if (!empty($secondsToLive) && isset($secondsToLive[1])) {
                $secondsToLive = $secondsToLive[1];
                $dateTime = new DateTime();
                $this->response->setHeader('expires', $dateTime->gmDate('D, d M Y H:i:s T', $dateTime->strToTime('+ '.$secondsToLive.' seconds')), true);
            }
        }

        if ($this->isDebugging()) {
            $apiResult['magentoDebugInfo'] = $this->_getDebugInfo();
        }

        return $apiResult;
    }


    /**
     * getSupportedCountries function.
     *
     * @access public
     * @return array
     */
    public function getSupportedCountries(): array
    {
        $client = $this->_prepareApiClient();

        try {
            return $client->internationalGetSupportedCountries();

        } catch (\Exception $e) {
            return [];
        }
    }


    /**
     * isDebugging function.
     *
     * @access public
     * @return bool
     */
    public function isDebugging(): bool
    {
        return (bool) $this->getStoreConfig('postcodenl_api/advanced_config/api_debug') && $this->developerHelper->isDevAllowed();
    }


    /**
     * getStoreConfig function.
     *
     * @access private
     * @param mixed $path
     * @return string|null
     */
    public function getStoreConfig($path): ?string
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }


    /**
     * _getKey function.
     *
     * @access private
     * @return string
     */
    private function _getKey(): string
    {
        return trim($this->getStoreConfig('postcodenl_api/general/api_key'));
    }


    /**
     * _getSecret function.
     *
     * @access private
     * @return string
     */
    private function _getSecret(): string
    {
        return trim($this->getStoreConfig('postcodenl_api/general/api_secret'));
    }


    /**
     * isPostCodeApiReady function.
     *
     * @access private
     * @return bool|array
     */
    private function isPostCodeApiReady()
    {
        if (empty($this->getStoreConfig('postcodenl_api/general/enabled'))) {
            return ['message' => __('Postcode.eu API not enabled.')];
        }

        if (empty($this->_getKey()) || empty($this->_getSecret())) {
            return [
                'message' => __('Postcode.eu API not configured.'),
                'info' => [__('Configure your `API key` and `API secret`.')]
            ];
        }

        if (!extension_loaded('curl')) {
            return [
                'message' => __('Cannot connect to Postcode.eu API: Server is missing support for CURL.')
            ];
        }

        $curlInfo = curl_version();
        if (!($curlInfo['features'] & CURL_VERSION_SSL)) {
            return [
                'message' => __('Cannot connect to Postcode.eu API: Server is missing SSL (https) support for CURL.')
            ];
        }

        return true;
    }


    /**
     * _getModuleInfo function.
     *
     * @access protected
     * @param mixed $moduleName
     * @return array|null
     */
    protected function _getModuleInfo($moduleName): ?array
    {
        $modules = $this->_getMagentoModules();

        if (!isset($modules[$moduleName])) {
            return null;
        }

        return $modules[$moduleName];
    }


    /**
     * _getMagentoModules function.
     *
     * @access private
     * @return array
     */
    private function _getMagentoModules(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $this->modules = [];

        foreach ($this->moduleList->getAll() as $name => $module) {
            $this->modules[$name] = [];
            foreach ($module as $key => $value) {
                if (in_array((string) $key, ['setup_version', 'name'])) {
                    $this->modules[$name][$key] = (string) $value;
                }
            }
        }

        return $this->modules;
    }


    /**
     * Get fixed country (ISO2) if there's only one allowed country.
     *
     * @access private
     * @return string\null
     */
    private function _getFixedCountry(): ?string
    {
        $allowedCountries = $this->getStoreConfig('general/country/allow');

        if (isset($allowedCountries) && strlen($allowedCountries) === 2)
        {
            return $allowedCountries;
        }

        return null;
    }


    /**
     * _getDebugInfo function.
     *
     * @access private
     * @return array
     */
    private function _getDebugInfo(): array
    {
        $debug = [
            'configuration' => [
                'key' => substr($this->_getKey(), 0, 6) . '[hidden]',
                'secret' => substr($this->_getSecret(), 0, 6) . '[hidden]',
                'debug' => $this->getStoreConfig('postcodenl_api/advanced_config/api_debug'),
            ],
            'modules' => $this->_getMagentoModules(),
        ];

        // Module version
        $moduleVersion = $this->_getModuleInfo('Flekto_Postcode');
        $debug['extensionVersion'] = 'unknown';
        if (!empty($moduleVersion) && isset($moduleVersion['setup_version'])) {
            $debug['extensionVersion'] = $moduleVersion['setup_version'];
        }

        // Magento version
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        $version = $productMetadata->getVersion();

        $debug['magentoVersion'] = 'Magento/'.$version;
        if ($this->_getModuleInfo('Enterprise_CatalogPermissions') !== null) {
            $debug['magentoVersion'] = 'MagentoEnterprise/'.$version;

        } elseif ($this->_getModuleInfo('Enterprise_Enterprise') !== null) {

            $debug['magentoVersion'] = 'MagentoProfessional/'.$version;
        }

        return $debug;
    }
}
