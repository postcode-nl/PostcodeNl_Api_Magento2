<?php

namespace Flekto\Postcode\Helper;

use Flekto\Postcode\Helper\StoreConfigHelper;
use Flekto\Postcode\Service\Exception\NotFoundException;
use Flekto\Postcode\Service\PostcodeApiClient;
use Magento\Developer\Helper\Data;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;


class ApiClientHelper extends AbstractHelper
{
    const API_ACCOUNT_STATUS_NEW = 'new';
    const API_ACCOUNT_STATUS_INVALID_CREDENTIALS = 'invalid_credentials';
    const API_ACCOUNT_STATUS_INACTIVE = 'inactive';
    const API_ACCOUNT_STATUS_ACTIVE = 'active';

    protected $_modules;
    protected $_moduleList;
    protected $_developerHelper;
    protected $_request;
    protected $_response;
    protected $_storeManager;
    protected $_client;
    protected $_localeResolver;
    protected $_countryCodeMap = [];
    protected $_storeConfigHelper;


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
     * @param LocaleResolver $localeResolver
     * @param StoreConfigHelper $storeConfigHelper
     * @return void
     */
    public function __construct(
        ModuleListInterface $moduleList,
        Data $developerHelper,
        Context $context,
        Request $request,
        Response $response,
        StoreManagerInterface $storeManager,
        LocaleResolver $localeResolver,
        StoreConfigHelper $storeConfigHelper
    ) {
        $this->_moduleList = $moduleList;
        $this->_developerHelper = $developerHelper;
        $this->_request = $request;
        $this->_response = $response;
        $this->_storeManager = $storeManager;
        $this->_localeResolver = $localeResolver;
        $this->_storeConfigHelper = $storeConfigHelper;
        parent::__construct($context);
    }

    /**
     * Get API client.
     *
     * @access public
     * @return PostcodeApiClient
     */
    public function getApiClient(): PostcodeApiClient
    {
        if (!isset($this->_client)) {
            $this->_client = new PostcodeApiClient($this->_getKey(), $this->_getSecret());
        }

        return $this->_client;
    }

    /**
     * Get settings to be used in frontend.
     *
     * @access public
     * @return array
     */
    public function getJsinit(): array
    {
        $settings = [
            'enabled_countries' => $this->_storeConfigHelper->getEnabledCountries(),
            'nl_input_behavior' => $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['nl_input_behavior']) ?? \Flekto\Postcode\Model\Config\Source\NlInputBehavior::ZIP_HOUSE,
            'show_hide_address_fields' => $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['show_hide_address_fields']) ?? \Flekto\Postcode\Model\Config\Source\ShowHideAddressFields::SHOW,
            'base_url' => $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB),
            'debug' => $this->isDebugging(),
            'fixedCountry' => $this->_getFixedCountry(),
            'change_fields_position' => $this->_storeConfigHelper->isSetFlag(StoreConfigHelper::PATH['change_fields_position']),
        ];

        return $settings;
    }

    /**
     * Get address autocomplete suggestions.
     *
     * @access public
     * @param string $context
     * @param string $term
     * @return array
     */
    public function getAddressAutocomplete(string $context, string $term): array
    {
        if (strlen($context) < 3) {
            $context = strtolower($this->getCountryIso3Code($context) ?? $context);
        }

        // API requires format 'nl-NL'
        $locale = str_replace('_', '-', $this->_localeResolver->getLocale());

        try {

            $client = $this->getApiClient();

            $sessionStr = $this->_request->getHeader($client::SESSION_HEADER_KEY);
            if (empty($sessionStr)) {
                $sessionStr = $this->_generateSessionString();
            }

            $response = $client->internationalAutocomplete($context, $term, $sessionStr, $locale);

            return $this->_prepareResponse($response, $client);

        } catch (\Exception $e) {
            return $this->_handleClientException($e);
        }
    }


    /**
     * Get address details
     *
     * @access public
     * @param string $context
     * @return array
     */
    public function getAddressDetails(string $context): array
    {
        try {

            $client = $this->getApiClient();

            $sessionStr = $this->_request->getHeader($client::SESSION_HEADER_KEY);
            if (empty($sessionStr)) {
                $sessionStr = $this->_generateSessionString();
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
        $address = null;

        preg_match('/^(\d{1,5})(\D.*)?$/i', $houseNumber, $matches);
        $houseNumber = isset($matches[1]) ? (int)$matches[1] : null;
        $houseNumberAddition = isset($matches[2]) ? trim($matches[2]) : null;

        if (is_null($houseNumber)) {
            return ['error' => true, 'message_details' => __('Invalid house number.')];
        }

        try {

            $client = $this->getApiClient();
            $address = $client->dutchAddressByPostcode($zipCode, $houseNumber, $houseNumberAddition);
            $address = $this->_prepareResponse($address, $client);
            $status = 'valid';

            if (
                (strcasecmp($address['houseNumberAddition'] ?? '', $houseNumberAddition ?? '') != 0)
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
            $response['message_details'] = __('Combination not found.');
        }

        if (!$this->isDebugging()) {
            if (empty($response['message_details'])) {
                $response['message_details'] = __('Something went wrong. Please try again.');
            }

            return $response;
        }

        $exceptionClass = get_class($exception);
        $response['message'] = sprintf(__('Exception %s occurred'), $exceptionClass) . $exception->getTraceAsString();

        $response['message_details'] = __($exception->getMessage());
        $response['magento_debug_info'] = $this->_getDebugInfo();

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
            $this->_response->setHeader('Cache-control', $clientResponseHeaders['cache-control'][0], true);
            $this->_response->setHeader('Pragma', 'cache', true);

            preg_match("#max-age=(.*?)$#sim", $clientResponseHeaders['cache-control'][0], $secondsToLive);
            if (!empty($secondsToLive) && isset($secondsToLive[1])) {
                $secondsToLive = $secondsToLive[1];
                $dateTime = new DateTime();
                $this->_response->setHeader('expires', $dateTime->gmDate('D, d M Y H:i:s T', $dateTime->strToTime('+ '.$secondsToLive.' seconds')), true);
            }
        }

        if ($this->isDebugging()) {
            $apiResult['magento_debug_info'] = $this->_getDebugInfo();
        }

        return $apiResult;
    }


    /**
     * Get supported countries from API.
     *
     * @access public
     * @return array
     */
    public function getSupportedCountries(): array
    {
        try {
            return $this->getApiClient()->internationalGetSupportedCountries();
        } catch (\Exception $e) {
            return [];
        }
    }


    /**
     * Get country ISO3 code from ISO2 code, or NULL if not found.
     *
     * @access public
     * @param string $iso2Code
     * @return string|null Lowercase ISO3 country code or NULL.
     */
    public function getCountryIso3Code(string $iso2Code): ?string
    {
        $mapKey = 'iso2_to_iso3';

        if (!isset($this->_countryCodeMap[$mapKey])) {
            $countries = $this->_storeConfigHelper->getSupportedCountries();
            $this->_countryCodeMap[$mapKey] = [];

            foreach ($countries as $country) {
                $this->_countryCodeMap[$mapKey][$country->iso2] = $country->iso3;
            }
        }

        return $this->_countryCodeMap[$mapKey][strtoupper($iso2Code)] ?? null;
    }


    /**
     * isDebugging function.
     *
     * @access public
     * @return bool
     */
    public function isDebugging(): bool
    {
        return $this->_storeConfigHelper->isSetFlag(StoreConfigHelper::PATH['api_debug'], ScopeInterface::SCOPE_STORE) && $this->_developerHelper->isDevAllowed();
    }


    /**
     * Get API key.
     *
     * @access protected
     * @return string
     */
    protected function _getKey(): string
    {
        return trim($this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['api_key']) ?? '');
    }


    /**
     * Get API secret.
     *
     * @access protected
     * @return string
     */
    protected function _getSecret(): string
    {
        return trim($this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['api_secret']) ?? '');
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
        if (isset($this->_modules)) {
            return $this->_modules;
        }

        $this->_modules = [];

        foreach ($this->_moduleList->getAll() as $name => $module) {
            $this->_modules[$name] = [];
            foreach ($module as $key => $value) {
                if (in_array((string) $key, ['setup_version', 'name'])) {
                    $this->_modules[$name][$key] = (string) $value;
                }
            }
        }

        return $this->_modules;
    }


    /**
     * Get fixed country (ISO2) if there's only one allowed country.
     *
     * @access private
     * @return string|null
     */
    private function _getFixedCountry(): ?string
    {
        $allowedCountries = $this->_storeConfigHelper->getValue('general/country/allow');

        if (isset($allowedCountries) && strlen($allowedCountries) === 2) {
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
                'debug' => $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['api_debug']),
            ],
            'modules' => $this->_getMagentoModules(),
        ];

        // Module version
        $debug['moduleVersion'] = $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['module_version']);

        // Magento version
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        $version = $productMetadata->getVersion();

        $debug['magentoVersion'] = 'Magento/' . $version;
        if ($this->_getModuleInfo('Enterprise_CatalogPermissions') !== null) {
            $debug['magentoVersion'] = 'MagentoEnterprise/' . $version;

        } elseif ($this->_getModuleInfo('Enterprise_Enterprise') !== null) {

            $debug['magentoVersion'] = 'MagentoProfessional/' . $version;
        }

        return $debug;
    }
}
