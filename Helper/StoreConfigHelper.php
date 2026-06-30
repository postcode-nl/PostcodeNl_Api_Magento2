<?php

namespace PostcodeEu\AddressValidation\Helper;

use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Developer\Helper\Data as DeveloperHelperData;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PostcodeEu\AddressValidation\Model\Config\Source\NlInputBehavior;
use PostcodeEu\AddressValidation\Model\Config\Source\ShowHideAddressFields;

class StoreConfigHelper extends AbstractHelper
{
    /** @var StoreManagerInterface */
    protected $_storeManager;
    /** @var DeveloperHelperData */
    protected $_developerHelper;
    /** @var EncryptorInterface */
    protected $_encryptor;
    /** @var CountryCollectionFactory */
    protected $_countryCollectionFactory;
    /** @var ResolverInterface */
    protected $_localeResolver;
    /** @var FormKey */
    protected $_formKey;
    /** @var AppState */
    protected $_appState;
    /** @var BackendUrlInterface */
    protected $_backendUrl;

    public const PATH = [
        // Status
        'module_version' => 'postcodenl_api/status/module_version',
        'supported_countries' => 'postcodenl_api/status/supported_countries',
        'account_name' => 'postcodenl_api/status/account_name',
        'account_status' => 'postcodenl_api/status/account_status',
        'api_max_failures' => 'postcodenl_api/status/api_max_failures',
        'api_failure_window_seconds' => 'postcodenl_api/status/api_failure_window_seconds',
        'api_cooldown_seconds' => 'postcodenl_api/status/api_cooldown_seconds',

        // General
        'enabled' => 'postcodenl_api/general/enabled',
        'api_key' => 'postcodenl_api/general/api_key',
        'api_secret' => 'postcodenl_api/general/api_secret',
        'nl_input_behavior' => 'postcodenl_api/general/nl_input_behavior',
        'show_hide_address_fields' => 'postcodenl_api/general/show_hide_address_fields',
        'allow_autofill_bypass' => 'postcodenl_api/general/allow_autofill_bypass',
        'change_fields_position' => 'postcodenl_api/general/change_fields_position',

        // Advanced
        'api_debug' => 'postcodenl_api/advanced_config/api_debug',
        'disabled_countries' => 'postcodenl_api/advanced_config/disabled_countries',
        'allow_pobox_shipping' => 'postcodenl_api/advanced_config/allow_pobox_shipping',
        'split_street_values' => 'postcodenl_api/advanced_config/split_street_values',
        'admin_address_autocomplete_behavior' => 'postcodenl_api/advanced_config/admin_address_autocomplete_behavior',
    ];

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param DeveloperHelperData $developerHelper
     * @param EncryptorInterface $encryptor
     * @param CountryCollectionFactory $countryCollectionFactory
     * @param ResolverInterface $localeResolver
     * @param FormKey $formKey
     * @param AppState $appState
     * @param BackendUrlInterface $backendUrl
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        DeveloperHelperData $developerHelper,
        EncryptorInterface $encryptor,
        CountryCollectionFactory $countryCollectionFactory,
        ResolverInterface $localeResolver,
        FormKey $formKey,
        AppState $appState,
        BackendUrlInterface $backendUrl
    ) {
        $this->_storeManager = $storeManager;
        $this->_developerHelper = $developerHelper;
        $this->_encryptor = $encryptor;
        $this->_countryCollectionFactory = $countryCollectionFactory;
        $this->_localeResolver = $localeResolver;
        $this->_formKey = $formKey;
        $this->_appState = $appState;
        $this->_backendUrl = $backendUrl;
        parent::__construct($context);
    }

    /**
     * Get store config value
     *
     * @access public
     * @param string $path - Full path or alias as specified in PATH constant.
     * @param int|string|null $storeId
     * @return string|null
     */
    public function getValue($path, $storeId = null): ?string
    {
        [$scopeType, $scopeCode] = $this->_getScopeContext($storeId);
        return $this->scopeConfig->getValue(static::PATH[$path] ?? $path, $scopeType, $scopeCode);
    }

    /**
     * Get store config flag
     *
     * @access public
     * @param string $path - Full path or alias as specified in PATH constant.
     * @param int|string|null $storeId
     * @return bool
     */
    public function isSetFlag($path, $storeId = null): bool
    {
        [$scopeType, $scopeCode] = $this->_getScopeContext($storeId);
        return $this->scopeConfig->isSetFlag(static::PATH[$path] ?? $path, $scopeType, $scopeCode);
    }

    /**
     * Get enabled status.
     *
     * @access public
     * @param int|string|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null): bool
    {
        return $this->isSetFlag('enabled', $storeId);
    }

    /**
     * Get supported countries from config.
     *
     * @access public
     * @param int|string|null $storeId
     * @return array
     */
    public function getSupportedCountries($storeId = null): array
    {
        return json_decode($this->getValue('supported_countries', $storeId) ?? '[]');
    }

    /**
     * Get supported countries, excluding disabled countries.
     *
     * @access public
     * @param int|string|null $storeId
     * @return array
     */
    public function getEnabledCountries($storeId = null): array
    {
        $supported = array_column($this->getSupportedCountries($storeId), 'iso2');
        $disabled = $this->getValue('disabled_countries', $storeId);

        if (empty($disabled)) {
            return $supported;
        }

        return array_values(array_diff($supported, explode(',', $disabled)));
    }

    /**
     * Get supported country names.
     *
     * @return array
     */
    public function getSupportedCountryNames(): array
    {
        $isoCodes = array_map(fn($country) => $country->iso2, $this->getSupportedCountries());
        $collection = $this->_countryCollectionFactory->create()->addFieldToFilter('country_id', ['in' => $isoCodes]);
        $locale = $this->_localeResolver->getLocale();
        $names = array_map(fn($country) => $country->getName(), $collection->getItems());
        \Collator::create($locale)->asort($names);

        return $names;
    }

    /**
     * Check if API credentials are set.
     *
     * @access public
     * @return bool
     */
    public function hasCredentials(): bool
    {
        $key = $this->getValue('api_key');
        $secret = $this->getValue('api_secret');

        return isset($key, $secret);
    }

    /**
     * Get API credentials, decrypting API secret.
     *
     * @access public
     * @return array
     */
    public function getCredentials(): array
    {
        $key = $this->getValue('api_key');
        $secret = $this->getValue('api_secret');

        if (isset($secret)
            && strpos($secret, ':') !== false // Magento\Framework\Encryption\Encryptor seperates parts by ':'.
        ) {
            $secret = $this->_encryptor->decrypt($secret);
        }

        return ['key' => $key ?? '', 'secret' => $secret ?? ''];
    }

    /**
     * Get current module version.
     *
     * @access public
     * @return string
     */
    public function getModuleVersion(): string
    {
        return $this->getValue('module_version') ?? 'UNKNOWN';
    }

    /**
     * Get settings to be used in frontend.
     *
     * @access public
     * @param int|string|null $storeId
     * @return array
     */
    public function getJsinit($storeId = null): array
    {
        $baseUrl = $this->getCurrentStoreBaseUrl($storeId);
        $isAdmin = false;

        try {
            $isAdmin = $this->_appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code not set
        }

        if ($isAdmin) {
            $apiBaseUrl = $this->_backendUrl->getUrl('postcode_eu/address/api');
            $apiActions = [
                'dutchAddressLookup' => $apiBaseUrl . 'method/postcode/postcode/{postcode}/house_number/{houseNumber}',
                'autocomplete' => $apiBaseUrl . 'method/autocomplete/context/{context}/term/{term}',
                'addressDetails' => $apiBaseUrl . 'method/address_details/context/{context}',
            ];
        } else {
            $apiBaseUrl = $baseUrl . 'postcode-eu/V1/';
            $formKey = $this->_formKey->getFormKey();
            $apiActions = [
                'dutchAddressLookup' => $apiBaseUrl . 'nl/address/{postcode}/{houseNumber}?form_key=' . $formKey,
                'autocomplete' => $apiBaseUrl . 'international/autocomplete/{context}/{term}?form_key=' . $formKey,
                'addressDetails' => $apiBaseUrl . 'international/address/{context}?form_key=' . $formKey,
                'validate' => $apiBaseUrl . 'international/validate/{country}?form_key=' . $formKey,
            ];
        }

        return [
            'enabled_countries' => $this->getEnabledCountries($storeId),
            'nl_input_behavior' => $this->getValue('nl_input_behavior', $storeId) ?? NlInputBehavior::ZIP_HOUSE,
            'show_hide_address_fields' => $this->getValue('show_hide_address_fields', $storeId) ?? ShowHideAddressFields::SHOW,
            'base_url' => $baseUrl,
            'api_actions' => $apiActions,
            'debug' => $this->isDebugging($storeId),
            'change_fields_position' => $this->isSetFlag('change_fields_position', $storeId),
            'allow_pobox_shipping' => $this->isSetFlag('allow_pobox_shipping', $storeId),
            'split_street_values' => $this->isSetFlag('split_street_values', $storeId),
        ];
    }

    /**
     * Get the base URL of the current store.
     *
     * @access public
     * @param int|string|null $storeId
     * @return string
     */
    public function getCurrentStoreBaseUrl($storeId = null): string
    {
        $currentStore = $this->_storeManager->getStore($storeId);
        return $this->_urlBuilder->getBaseUrl(['_store' => $currentStore->getCode()]);
    }

    /**
     * Check if debugging is active.
     *
     * @access public
     * @param int|string|null $storeId
     * @return bool
     */
    public function isDebugging($storeId = null): bool
    {
        return $this->isSetFlag('api_debug', $storeId) && $this->_developerHelper->isDevAllowed();
    }

    /**
     * Get scope from request params.
     *
     * @return array - Scope type and id
     */
    public function getScopeFromRequest(): array
    {
        $storeId = $this->_request->getParam(ScopeInterface::SCOPE_STORE);

        if ($storeId !== null) {
            return [ScopeInterface::SCOPE_STORES, (int)$storeId];
        }

        $websiteId = $this->_request->getParam(ScopeInterface::SCOPE_WEBSITE);

        if ($websiteId !== null) {
            return [ScopeInterface::SCOPE_WEBSITES, (int)$websiteId];
        }

        return [\Magento\Framework\App\ScopeInterface::SCOPE_DEFAULT, 0];
    }

    /**
     * Get scope type and code based on request.
     *
     * @param int|string|null $storeId
     * @return array - Scope type and id
     */
    private function _getScopeContext($storeId = null): array
    {
        if ($storeId !== null) {
            return [ScopeInterface::SCOPE_STORES, $storeId];
        }

        try {
            // Check for admin area.
            if ($this->_appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
                [$scope, $scopeId] = $this->getScopeFromRequest();

                return [$scope, $scopeId ?: null];
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code not set, fall through.
        }

        return [ScopeInterface::SCOPE_STORES, null];
    }
}
