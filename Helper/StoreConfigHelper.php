<?php

namespace Flekto\Postcode\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class StoreConfigHelper extends AbstractHelper
{
    public const PATH = [
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

        // Status
        'module_version' => 'postcodenl_api/status/module_version',
        'supported_countries' => 'postcodenl_api/status/supported_countries',
        'account_name' => 'postcodenl_api/status/account_name',
        'account_status' => 'postcodenl_api/status/account_status',
    ];

    /**
     * Get store config value
     *
     * @access public
     * @param mixed $path
     * @return string|null
     */
    public function getValue($path): ?string
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get store config flag
     *
     * @access public
     * @param mixed $path
     * @return bool|null
     */
    public function isSetFlag($path): ?bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get enabled status.
     *
     * @access public
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->isSetFlag(static::PATH['enabled']);
    }

    /**
     * Get supported countries from config.
     *
     * @access public
     * @return array
     */
    public function getSupportedCountries(): array
    {
        return json_decode($this->getValue(static::PATH['supported_countries']) ?? '[]');
    }

    /**
     * Get supported countries, excluding disabled countries.
     *
     * @access public
     * @return array
     */
    public function getEnabledCountries(): array
    {
        $supported = array_column($this->getSupportedCountries(), 'iso2');
        $disabled = $this->getValue(static::PATH['disabled_countries']);

        if (empty($disabled)) {
            return $supported;
        }

        return array_values(array_diff($supported, explode(',', $disabled)));
    }

    /**
     * Check if API credentials are set.
     *
     * @access public
     * @return bool
     */
    public function hasCredentials(): bool
    {
        $key = $this->getValue(static::PATH['api_key']);
        $secret = $this->getValue(static::PATH['api_secret']);

        return isset($key, $secret);
    }

}
