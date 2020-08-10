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
     * @return void
     */
    public function getJsinit()
    {
        if (!$this->getStoreConfig('postcodenl_api/general/enabled')) {
            return [];
        }

        $settings = [
            "enabled" => $this->getStoreConfig('postcodenl_api/general/enabled'),
            "supported_countries" => json_encode($this->formatSupportedCountriesJs($this->getStoreConfig('postcodenl_api/general/supported_countries'))),
            "nl_input_behavior" => (!empty($this->getStoreConfig('postcodenl_api/general/nl_input_behavior')) ? $this->getStoreConfig('postcodenl_api/general/nl_input_behavior') : 'zip_house'),
            "show_hide_address_fields" => (!empty($this->getStoreConfig('postcodenl_api/general/show_hide_address_fields')) ? $this->getStoreConfig('postcodenl_api/general/show_hide_address_fields') : 'show'),
            "base_url" => $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB),
            "debug" => $this->isDebugging(),
            "translations" => [
                'flekto_nl_zip_label' => __('Postcode and house number'),
                'flekto_nl_zip_placeholder' => __('1234AB 1'),
                'flekto_nl_zip_warning' => __('Enter a postcode and house number.'),
            ]
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

        $countries = explode(", ", $countries);
        $countriesReturn = [];
        if (!empty($countries)) {
            foreach ($countries as $country) {
                $countriesReturn[] = CountryCodeConvertorHelper::alpha3ToAlpha2($country);
            }
        }

        return $countriesReturn;
    }


    /**
     * prepareApiClient function.
     *
     * @access private
     * @return PostcodeApiClient
     */
    private function prepareApiClient()
    {
        $isApiReady = $this->isPostCodeApiReady();
        if ($isApiReady !== true) {
            return $isApiReady;
        }

        $client = new PostcodeApiClient($this->getKey(), $this->getSecret());
        return $client;
    }


    /**
     * getAddressAutocomplete function.
     *
     * @access public
     * @param String $context
     * @param String $term
     * @return Array
     */
    public function getAddressAutocomplete(String $context, String $term)
    {
        $context = CountryCodeConvertorHelper::alpha2ToAlpha3($context);
        $client = $this->prepareApiClient();

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
            $response['session_id'] = $sessionStr;

            return $this->prepareResponse($response, $client);

        } catch (\Exception $e) {
            return $this->handleClientException($e);
        }
    }


    /**
     * getAddressDetails function.
     *
     * @access public
     * @param String $context
     * @param String $dispatchCountry
     * @return void
     */
    public function getAddressDetails(String $context, String $dispatchCountry="")
    {
        if (strlen($dispatchCountry) > 2) {
            $dispatchCountry = CountryCodeConvertorHelper::alpha2ToAlpha3($dispatchCountry);
        }

        $client = $this->prepareApiClient();

        try {

            $sessionStr = $this->request->getHeader($client::SESSION_HEADER_KEY);
            if (empty($sessionStr)) {
                $sessionStr = $this->generateSessionString();
            }

            $response = $client->internationalGetDetails($context, $sessionStr);
            return $this->prepareResponse($response, $client);

        } catch (\Exception $e) {
            return $this->handleClientException($e);
        }
    }


    /**
     * getNlAddress function.
     *
     * @access public
     * @param String $zipCode
     * @param String $houseNumber
     * @return void
     */
    public function getNlAddress(String $zipCode, String $houseNumber)
    {
        $client = $this->prepareApiClient();

        $houseNumber = (int) preg_replace('/[^0-9]/', '', $houseNumber);

        try {
            $response = $client->dutchAddressByPostcode($zipCode, $houseNumber/*  , ?string $houseNumberAddition = null */);
            return $this->prepareResponse($response, $client);

        } catch (\Exception $e) {
            return $this->handleClientException($e);
        }
    }


    /**
     * generateSessionString function.
     *
     * @access private
     * @return void
     */
    private function generateSessionString()
    {
        return bin2hex(random_bytes(8));
    }


    /**
     * handleClientException function.
     *
     * @access private
     * @param mixed $exception
     * @return void
     */
    private function handleClientException($exception)
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
        $response['debugInfo'] = $this->getDebugInfo();

        return $response;
    }


    /**
     * prepareResponse function.
     *
     * @access private
     * @param mixed $apiResult
     * @param mixed $client
     * @return void
     */
    private function prepareResponse($apiResult, $client)
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

        $response['response'] = $apiResult;
        if ($this->isDebugging()) {
            $response['debugInfo'] = $this->getDebugInfo();
        }

        return $response;
    }


    /**
     * getSupportedCountries function.
     *
     * @access public
     * @return void
     */
    public function getSupportedCountries()
    {
        $client = $this->prepareApiClient();

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
     * @return void
     */
    public function isDebugging()
    {
        return (bool) $this->getStoreConfig('postcodenl_api/advanced_config/api_debug') && $this->developerHelper->isDevAllowed();
    }


    /**
     * getStoreConfig function.
     *
     * @access private
     * @param mixed $path
     * @return void
     */
    public function getStoreConfig($path)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }


    /**
     * getKey function.
     *
     * @access private
     * @return void
     */
    private function getKey()
    {
        return trim($this->getStoreConfig('postcodenl_api/general/api_key'));
    }


    /**
     * getSecret function.
     *
     * @access private
     * @return void
     */
    private function getSecret()
    {
        return trim($this->getStoreConfig('postcodenl_api/general/api_secret'));
    }


    /**
     * isPostCodeApiReady function.
     *
     * @access private
     * @return void
     */
    private function isPostCodeApiReady()
    {
        if (empty($this->getStoreConfig('postcodenl_api/general/enabled'))) {
            return ['message' => __('Postcode.nl API not enabled.')];
        }

        if (empty($this->getKey()) || empty($this->getSecret())) {
            return [
                'message' => __('Postcode.nl API not configured.'),
                'info' => [__('Configure your `API key` and `API secret`.')]
            ];
        }

        if (!extension_loaded('curl')) {
            return [
                'message' => __('Cannot connect to Postcode.nl API: Server is missing support for CURL.')
            ];
        }

        $curlInfo = curl_version();
        if (!($curlInfo['features'] & CURL_VERSION_SSL)) {
            return [
                'message' => __('Cannot connect to Postcode.nl API: Server is missing SSL (https) support for CURL.')
            ];
        }

        return true;
    }


    /**
     * getModuleInfo function.
     *
     * @access protected
     * @param mixed $moduleName
     * @return void
     */
    protected function getModuleInfo($moduleName)
    {
        $modules = $this->getMagentoModules();

        if (!isset($modules[$moduleName])) {
            return null;
        }

        return $modules[$moduleName];
    }


    /**
     * getMagentoModules function.
     *
     * @access private
     * @return void
     */
    private function getMagentoModules()
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
     * getDebugInfo function.
     *
     * @access private
     * @return void
     */
    private function getDebugInfo()
    {
        $debug = [
            'configuration' => [
                'key' => substr($this->getKey(), 0, 6) . '[hidden]',
                'secret' => substr($this->getSecret(), 0, 6) . '[hidden]',
                'debug' => $this->getStoreConfig('postcodenl_api/advanced_config/api_debug'),
            ],
            'modules' => $this->getMagentoModules(),
        ];

        // Module version
        $moduleVersion = $this->getModuleInfo('Flekto_Postcode');
        $debug['extensionVersion'] = 'unknown';
        if (!empty($moduleVersion) && isset($moduleVersion['setup_version'])) {
            $debug['extensionVersion'] = $moduleVersion['setup_version'];
        }

        // Magento version
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        $version = $productMetadata->getVersion();

        $debug['magentoVersion'] = 'Magento/'.$version;
        if ($this->getModuleInfo('Enterprise_CatalogPermissions') !== null) {
            $debug['magentoVersion'] = 'MagentoEnterprise/'.$version;

        } elseif ($this->getModuleInfo('Enterprise_Enterprise') !== null) {

            $debug['magentoVersion'] = 'MagentoProfessional/'.$version;
        }

        return $debug;
    }
}
