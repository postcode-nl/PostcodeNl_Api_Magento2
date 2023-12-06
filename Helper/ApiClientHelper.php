<?php

namespace Flekto\Postcode\Helper;

use Exception;
use Flekto\Postcode\Helper\StoreConfigHelper;
use Flekto\Postcode\Service\Exception\NotFoundException;
use Flekto\Postcode\Service\PostcodeApiClient;
use Magento\Developer\Helper\Data;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;

class ApiClientHelper extends AbstractHelper
{
    public const API_ACCOUNT_STATUS_NEW = 'new';
    public const API_ACCOUNT_STATUS_INVALID_CREDENTIALS = 'invalid_credentials';
    public const API_ACCOUNT_STATUS_INACTIVE = 'inactive';
    public const API_ACCOUNT_STATUS_ACTIVE = 'active';

    protected $_modules;
    protected $_moduleList;
    protected $_developerHelper;
    protected $_request;
    protected $_response;
    protected $_client;
    protected $_localeResolver;
    protected $_countryCodeMap = [];
    protected $_storeConfigHelper;
    protected $_productMetadata;

    /**
     * __construct function.
     *
     * @access public
     * @param ModuleListInterface $moduleList
     * @param Data $developerHelper
     * @param Context $context
     * @param Request $request
     * @param Response $response
     * @param PostcodeApiClient $client
     * @param LocaleResolver $localeResolver
     * @param StoreConfigHelper $storeConfigHelper
     * @param ProductMetadataInterface $productMetadata
     * @return void
     */
    public function __construct(
        ModuleListInterface $moduleList,
        Data $developerHelper,
        Context $context,
        Request $request,
        Response $response,
        PostcodeApiClient $client,
        LocaleResolver $localeResolver,
        StoreConfigHelper $storeConfigHelper,
        ProductMetadataInterface $productMetadata
    ) {
        $this->_moduleList = $moduleList;
        $this->_developerHelper = $developerHelper;
        $this->_request = $request;
        $this->_response = $response;
        $this->_client = $client;
        $this->_localeResolver = $localeResolver;
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_productMetadata = $productMetadata;
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
        return $this->_client;
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

        $language = explode('_', $this->_localeResolver->getLocale())[0];

        try {

            $client = $this->getApiClient();
            $sessionId = $this->_getSessionId();
            $response = $client->internationalAutocomplete($context, $term, $sessionId, $language);
            return $this->_prepareResponse($response, $client);

        } catch (\Exception $e) {
            return $this->_handleClientException($e);
        }
    }

    /**
     * Get address details.
     *
     * @access public
     * @param string $context
     * @return array
     */
    public function getAddressDetails(string $context): array
    {
        try {

            $client = $this->getApiClient();
            $sessionId = $this->_getSessionId();
            $response = $client->internationalGetDetails($context, $sessionId);
            return $this->_prepareResponse($response, $client);

        } catch (\Exception $e) {
            return $this->_handleClientException($e);
        }
    }

    /**
     * Get session identifier.
     *
     * @return string|null Session identifier, or null if not found.
     */
    private function _getSessionId(): ?string
    {
        $id = $this->_request->getHeader(PostcodeApiClient::SESSION_HEADER_KEY);
        if ($id === false) {
            return null; // Client will generate its own session id if null.
        }

        return $id;
    }

    /**
     * Get Dutch address.
     *
     * @access public
     * @param string $zipCode
     * @param string $houseNumber
     * @return array
     */
    public function getNlAddress(string $zipCode, string $houseNumber): array
    {
        $address = null;
        $matches = [];

        preg_match('/^(\d{1,5})(\D.*)?$/i', $houseNumber, $matches);
        $houseNumber = isset($matches[1]) ? (int)$matches[1] : null;
        $houseNumberAddition = isset($matches[2]) ? trim($matches[2]) : null;

        if (null === $houseNumber) {
            return ['error' => true, 'message_details' => __('Invalid house number.')];
        }

        try {

            $client = $this->getApiClient();
            $address = $client->dutchAddressByPostcode($zipCode, $houseNumber, $houseNumberAddition);
            $address = $this->_prepareResponse($address, $client);
            $status = 'valid';

            if ((strcasecmp($address['houseNumberAddition'] ?? '', $houseNumberAddition ?? '') != 0)
                ||
                (!empty($address['houseNumberAdditions']) && null === $address['houseNumberAddition'])
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

        if ($this->_storeConfigHelper->isDebugging()) {
            $out['debug'] = [
                'parsedHouseNumber' => $houseNumber,
                'parsedHouseNumberAddition' => $houseNumberAddition,
            ];
        }

        return $out;
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
        $response = [];
        $response['error'] = true;

        // only in this case we actually pass error
        // to front-end without debug option needed
        if ($exception instanceof NotFoundException) {
            $response['message_details'] = __('Combination not found.');
        }

        if (!$this->_storeConfigHelper->isDebugging()) {
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
     * @param array $apiResult
     * @param PostcodeApiClient $client
     * @return array
     */
    private function _prepareResponse(array $apiResult, PostcodeApiClient $client): array
    {
        $headers = $client->getMostRecentResponseHeaders();
        $this->_repeatCacheControlHeader($headers);

        if ($this->_storeConfigHelper->isDebugging()) {
            $apiResult['magento_debug_info'] = $this->_getDebugInfo();
        }

        return $apiResult;
    }

    /**
     * Repeat cache control header.
     *
     * @param array $apiResponseHeaders
     */
    protected function _repeatCacheControlHeader(array $apiResponseHeaders): void
    {
        if (isset($apiResponseHeaders['cache-control'])) {
            $this->_response->setHeader('cache-control', $apiResponseHeaders['cache-control']);
        }
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
     * _getDebugInfo function.
     *
     * @access private
     * @return array
     */
    private function _getDebugInfo(): array
    {
        $credentials = $this->_storeConfigHelper->getCredentials();
        $debug = [
            'configuration' => [
                'key' => $credentials['key'],
                'secret' => substr_replace($credentials['secret'], '***', 3, -3),
                'debug' => $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['api_debug']),
            ],
            'modules' => $this->_getMagentoModules(),
        ];

        // Module version
        $debug['moduleVersion'] = $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['module_version']);

        // Magento version
        $version = $this->_productMetadata->getVersion();

        $debug['magentoVersion'] = 'Magento/' . $version;
        if ($this->_getModuleInfo('Enterprise_CatalogPermissions') !== null) {
            $debug['magentoVersion'] = 'MagentoEnterprise/' . $version;

        } elseif ($this->_getModuleInfo('Enterprise_Enterprise') !== null) {

            $debug['magentoVersion'] = 'MagentoProfessional/' . $version;
        }

        $debug['client'] = $this->getApiClient()->getUserAgent();
        $debug['session'] = $this->_getSessionId();
        return $debug;
    }
}
