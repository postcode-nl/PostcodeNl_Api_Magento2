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
     * @return void
     */
    public function __construct(ModuleListInterface $moduleList, Data $developerHelper, Context $context, Request $request, Response $response) {
        $this->moduleList = $moduleList;
        $this->developerHelper = $developerHelper;
        $this->request = $request;
        $this->response = $response;
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
            "debug" => $this->isDebugging()
        ];

        return $settings;
    }


    /**
     * formatSupportedCountriesJs function.
     *
     * @access public
     * @param String $countries (default: "")
     * @return String
     */
    public function formatSupportedCountriesJs(String $countries = "")
    {
        if (empty($countries)) return "";

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
        $storeLang = $objectManager->get('Magento\Framework\Locale\Resolver')->getLocale();

        try {
            $sessionStr = $this->generateSessionString();
            $response = $client->internationalAutocomplete($context, $term, $sessionStr, $storeLang);
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
     * @param String $session
     * @return void
     */
    public function getAddressDetails(String $context, String $dispatchCountry="")
    {
        if (strlen($dispatchCountry) > 2) {
            $dispatchCountry = CountryCodeConvertorHelper::alpha2ToAlpha3($dispatchCountry);
        }

        $client = $this->prepareApiClient();

        try {
            $ression = $this->request->getHeader($client::SESSION_HEADER_KEY);
            $response = $client->internationalGetDetails($context, $ression);
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
        $response['messageTarget'] = 'housenumber';
        $response['useManual'] = true;

        if (!$this->isDebugging()) {
            return $response;
        }

        $exceptionClass = get_class($exception);
        $response['message'] = "Exception $exceptionClass occurred: ".$exception->getTraceAsString();
        $response['message_details'] = $exception->getMessage();
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
