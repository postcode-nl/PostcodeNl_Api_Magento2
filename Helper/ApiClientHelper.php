<?php

namespace Flekto\Postcode\Helper;

use Exception;
use Flekto\Postcode\Helper\StoreConfigHelper;
use Flekto\Postcode\Service\Exception\NotFoundException;
use Flekto\Postcode\Service\PostcodeApiClient;
use Magento\Customer\Helper\Address as AddressHelper;
use Magento\Developer\Helper\Data;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Psr\Log\LoggerInterface;

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
    protected $_regionFactory;
    protected $_addressHelper;
    protected $_logger;

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
     * @param RegionFactory $regionFactory
     * @param AddressHelper $addressHelper
     * @param LoggerInterface $logger
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
        ProductMetadataInterface $productMetadata,
        RegionFactory $regionFactory,
        AddressHelper $addressHelper,
        LoggerInterface $logger
    ) {
        $this->_moduleList = $moduleList;
        $this->_developerHelper = $developerHelper;
        $this->_request = $request;
        $this->_response = $response;
        $this->_client = $client;
        $this->_localeResolver = $localeResolver;
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_productMetadata = $productMetadata;
        $this->_regionFactory = $regionFactory;
        $this->_addressHelper = $addressHelper;
        $this->_logger = $logger;
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
            $response['region'] = $this->_getRegionFromDetails($response);
            $response['streetLines'] = $this->_getStreetLines($response);
            $response = $this->_prepareResponse($response, $client);

            return $response;

        } catch (\Exception $e) {
            return $this->_handleClientException($e);
        }
    }

    /**
     * Get region from an address details response.
     *
     * @param array $addressDetails
     * @return array - Region id and name, if found.
     */
    protected function _getRegionFromDetails(array $addressDetails): array
    {
        $countryIso2 = $addressDetails['country']['iso2Code'];
        switch ($countryIso2) {
            case 'NL':
                $region = $addressDetails['details']['nldProvince']['name'];
                break;
            case 'BE':
                if (isset($addressDetails['details']['belProvince'])) {
                    $region = $addressDetails['details']['belProvince']['primaryName'];
                } else {
                    $region = $addressDetails['details']['belRegion']['primaryName'];
                }
                break;
            case 'DE':
                $region = $addressDetails['details']['deuFederalState']['name'];
                break;
            case 'LU':
                $region = $addressDetails['details']['luxCanton']['name'];
                break;
            case 'ES':
                $region = $addressDetails['details']['espProvince']['name'];
                $regions = explode('/', $region);
                break;
            case 'CH':
                $region = $addressDetails['details']['cheCanton']['name'];
                break;
        }

        if (isset($region)) {
            foreach ($regions ?? [$region] as $r) { // Use $regions array to try alternative names.
                ['id' => $id, 'name' => $name] = $this->_getRegionByName($r, $countryIso2);
                if (isset($id)) {
                    break;
                }
            }
        }

        return ['id' => $id ?? null, 'name' => $name ?? $region ?? null];
    }

    /**
     * Get region by name.
     *
     * @param string $name
     * @param string $countryIso2
     * @return array - Region id and name, if found.
     */
    protected function _getRegionByName(string $name, string $countryIso2): array
    {
        $regionFactory = $this->_regionFactory->create()->loadByName($name, $countryIso2);
        if ($regionFactory->hasData()) {
            $id = $regionFactory->getId();
            $name = $regionFactory->getName();
        }

        return ['id' => $id ?? null, 'name' => $name ?? null];
    }

    /**
     * Get street lines from an address details response.
     *
     * The amount of lines is limited by the configured number of lines in a street address.
     *
     * @param array $addressDetails
     * @return array - Street lines formatted according to country and config.
     */
    protected function _getStreetLines(array $addressDetails): array
    {
        $address = $addressDetails['address'];
        $countryIso2 = $addressDetails['country']['iso2Code'];
        $lastLineIndex = $this->_addressHelper->getStreetLines() - 1;

        if ($this->_storeConfigHelper->isSetFlag('split_street_values')) {
            // Assume fields are fixed street parts, independent of country.
            $parts = [
                $address['street'],
                $address['buildingNumber'] ?? '',
                $address['buildingNumberAddition'] ?? '',
            ];
            $lines = array_slice($parts, 0, $lastLineIndex);
            $lines[] = implode(' ', array_slice($parts, $lastLineIndex));
        } elseif ($countryIso2 === 'LU') {
            $lines = [$address['building'] . ', ' . $address['street']];
        } elseif ($countryIso2 === 'FR') {
            $lines = [trim($address['building'] . ' ' . $address['street'])];
        } elseif ($countryIso2 === 'GB') {
            $building = $addressDetails['details']['gbrBuilding'];
            if ($address['street'] === '') {
                $separator = '';
            } elseif ($building['number'] === null && $building['addition'] === null) {
                $separator = ', ';
            } else {
                $separator = ' ';
            }

            // Support multiple lines in British address.
            $parts = explode(', ', $address['building'] . $separator . $address['street']);
            $lines = array_slice($parts, 0, $lastLineIndex);
            $lines[] = implode(', ', array_slice($parts, $lastLineIndex));
        } else {
            $lines = [trim($address['street'] . ' ' . $address['building'])];
        }

        return $lines;
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

        if (!preg_match('/^[1-9]\d{3}\s?[a-z]{2}$/i', $zipCode)) {
            return ['error' => true, 'message' => __('Invalid zip code.')];
        }

        preg_match('/^(\d{1,5})(\D.*)?$/i', $houseNumber, $matches);
        $houseNumber = isset($matches[1]) ? (int)$matches[1] : null;
        $houseNumberAddition = isset($matches[2]) ? trim($matches[2]) : null;

        if (null === $houseNumber) {
            return ['error' => true, 'message' => __('Invalid house number.')];
        }

        try {

            $client = $this->getApiClient();
            $address = $client->dutchAddressByPostcode($zipCode, $houseNumber, $houseNumberAddition);
            $status = 'valid';

            if ((strcasecmp($address['houseNumberAddition'] ?? '', $houseNumberAddition ?? '') != 0)
                || (!empty($address['houseNumberAdditions']) && null === $address['houseNumberAddition'])
            ) {
                $status = 'houseNumberAdditionIncorrect';
            }
        } catch (NotFoundException $e) {
            return ['status' => 'notFound', 'address' => null];
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

        if ($this->_storeConfigHelper->isDebugging()) {
            $address['debug'] = [
                'parsedHouseNumber' => $houseNumber,
                'parsedHouseNumberAddition' => $houseNumberAddition,
            ];
        }

        $result = ['address' => $address, 'status' => $status];

        return $this->_prepareResponse($result, $client);
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
        if (!$exception instanceof \Flekto\Postcode\Service\Exception\NotFoundException) {
            $this->_logger->error($exception->getMessage(), ['exception' => $exception]);
        }

        $result = ['error' => true, 'message' => __('Something went wrong. Please try again.')];

        if ($this->_storeConfigHelper->isDebugging()) {
            $result['exception'] = __('Exception %1 occurred.', get_class($exception)) . $exception->getTraceAsString();
            $result['message'] = __($exception->getMessage());
            $result['magento_debug_info'] = $this->_getDebugInfo();
        } elseif ($exception instanceof NotFoundException) {
            // Only in this case we actually pass error to the front-end without debug option needed.
            $result['message'] = __($exception->getMessage());
        }

        return $result;
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
     * Validate a full address, correcting and completing all parts of the address.
     *
     * @access public
     * @see \Flekto\Postcode\Service\PostcodeApiClient::validateAddress()
     * @return array
     */
    public function validateAddress(): array
    {
        $args = func_get_args();
        if (strlen($args[0]) === 2) {
            $args[0] = $this->getCountryIso3Code($args[0]); // Support country ISO 2 code.
        }

        try {
            $client = $this->getApiClient();
            $response = $client->validateAddress(...$args);

            foreach ($response['matches'] as &$m) {
                if (in_array($m['status']['validationLevel'], ['Building', 'BuildingPartial'], true)) {
                    $m['region'] = $this->_getRegionFromDetails($m);
                    $m['streetLines'] = $this->_getStreetLines($m);
                }
            }

            return $this->_prepareResponse($response, $client);

        } catch (\Exception $e) {
            return $this->_handleClientException($e);
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

    public function getAccountInfo(): array
    {
        try {
            return $this->getApiClient()->accountInfo();
        } catch (\Exception $e) {
            return [];
        }
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
        if (!isset($this->_modules)) {
            $this->_modules = array_map(
                fn ($module) => ['name' => $module['name'], 'setup_version' => $module['setup_version'] ?? ''],
                $this->_moduleList->getAll()
            );
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
            ],
            'modules' => $this->_getMagentoModules(),
        ];

        // Module version
        $debug['moduleVersion'] = $this->_storeConfigHelper->getModuleVersion();

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
