<?php

namespace PostcodeEu\AddressValidation\Block\System\Config;

use Magento\Backend\Block\Template;
use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;
use Magento\Framework\App\Cache\TypeListInterface as CacheTypeList;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use PostcodeEu\AddressValidation\Helper\ApiClientHelper;
use PostcodeEu\AddressValidation\Helper\Data as DataHelper;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Model\UpdateNotification\UpdateNotifier;

class Status extends Template implements RendererInterface
{
    public const CACHE_ID = 'postcode-eu-status';
    public const CACHE_LIFETIME_SECONDS = 3600;

    /** @var string */
    protected $_template = 'PostcodeEu_AddressValidation::system/config/status.phtml';
    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $_scopeConfig;
    /** @var StoreConfigHelper */
    protected $_storeConfigHelper;
    /** @var ApiClientHelper */
    protected $_apiClientHelper;
    /** @var ConfigInterface */
    protected $_resourceConfig;
    /** @var CacheTypeList */
    protected $_cacheTypeList;
    /** @var CacheFrontendPool */
    protected $_cacheFrontendPool;
    /** @var DataHelper */
    protected $_dataHelper;
    /** @var UpdateNotifier */
    protected $_updateNotifier;

    /** @var array|null */
    private $_cachedData;

    /**
     * @param Template\Context $context
     * @param StoreConfigHelper $storeConfigHelper
     * @param ApiClientHelper $apiClientHelper
     * @param ConfigInterface $resourceConfig
     * @param CacheTypeList $cacheTypeList
     * @param CacheFrontendPool $cacheFrontendPool
     * @param DataHelper $dataHelper
     * @param UpdateNotifier $updateNotifier
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        StoreConfigHelper $storeConfigHelper,
        ApiClientHelper $apiClientHelper,
        ConfigInterface $resourceConfig,
        CacheTypeList $cacheTypeList,
        CacheFrontendPool $cacheFrontendPool,
        DataHelper $dataHelper,
        UpdateNotifier $updateNotifier,
        array $data = []
    ) {
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_apiClientHelper = $apiClientHelper;
        $this->_resourceConfig = $resourceConfig;
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheFrontendPool = $cacheFrontendPool;
        $this->_dataHelper = $dataHelper;
        $this->_updateNotifier = $updateNotifier;

        parent::__construct($context, $data);
    }

    /**
     * Render template.
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $this->_cachedData = $this->_getCachedData();
        $this->_notifyUpdate();

        /** @noinspection PhpUndefinedMethodInspection */
        $this->setElement($element);

        return $this->toHtml();
    }

    /**
     * Get config to be used in the status template.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'enabled' => $this->_storeConfigHelper->isEnabled(),
            'module_version' => $this->_storeConfigHelper->getModuleVersion(),
            'supported_countries' => $this->_storeConfigHelper->getSupportedCountryNames(),
            'account_name' => $this->_storeConfigHelper->getValue('account_name'),
            'account_status' => $this->_storeConfigHelper->getValue('account_status'), // Defaults to "new", see etc/config.xml.
            'has_credentials' => $this->_storeConfigHelper->hasCredentials(),
        ];
    }

    /**
     * Get cached account info.
     *
     * @return array
     */
    public function getAccountInfo(): array
    {
        return $this->_cachedData['accountInfo'] ?? [];
    }

    /**
     * Get cached module info.
     *
     * @return array
     */
    public function getModuleInfo(): array
    {
        return $this->_cachedData['moduleInfo'] ?? [];
    }

    /**
     * Get short description of API status.
     *
     * @return string
     */
    public function getApiStatusDescription(): string
    {
        $status = $this->_storeConfigHelper->getValue('account_status');

        switch ($status) {
            case ApiClientHelper::API_ACCOUNT_STATUS_NEW:
                return __('new');
            case ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE:
                return __('active');
            case ApiClientHelper::API_ACCOUNT_STATUS_INVALID_CREDENTIALS:
                return __('invalid key and/or secret');
            case ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE:
                return __('inactive');
            default:
                throw new Status\Exception(__('Invalid account status value.'));
        }
    }

    /**
     * Get hint about API status.
     *
     * @return string
     */
    public function getApiStatusHint(): string
    {
        $status = $this->_storeConfigHelper->getValue('account_status');

        switch ($status) {
            case ApiClientHelper::API_ACCOUNT_STATUS_NEW:
                return __('Enter your Postcode.eu API key and secret to connect.');
            case ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE:
                return __('The Postcode.eu API is successfully connected.');
            case ApiClientHelper::API_ACCOUNT_STATUS_INVALID_CREDENTIALS:
                return __('The API key or secret is incorrect. Please check your credentials.');
            case ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE:
                return __('Your Postcode.eu subscription is inactive. Please log in to your account to resolve this.');
            default:
                throw new Status\Exception(__('Invalid account status value.'));
        }
    }

    /**
     * Check if API status is active.
     *
     * @return bool
     */
    public function isStatusActive(): bool
    {
        return $this->_storeConfigHelper->getValue('account_status') === ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE;
    }

    /**
     * Check if API is available.
     *
     * @return bool
     */
    public function isApiAvailable(): bool
    {
        return $this->_apiClientHelper->isApiAvailable();
    }

    /**
     * Get cached data.
     *
     * @return array
     */
    private function _getCachedData(): array
    {
        $cache = $this->_cacheFrontendPool->get(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
        [$scopeType, $scopeId] = $this->_storeConfigHelper->getScopeFromRequest();
        $cacheId = implode('-', [self::CACHE_ID, $scopeType, $scopeId]);
        $cachedData = $cache->load($cacheId);

        if ($cachedData === false) {
            $data = [];
            $data['accountInfo'] = $this->_getAccountInfo();
            $data['moduleInfo'] = $this->_dataHelper->getModuleInfo();

            $payload = json_encode($data);

            if ($payload === false) {
                $this->_logger->warning('Unable to encode Postcode.eu status cache payload.');

                return $data;
            }

            $cache->save($payload, $cacheId, [], self::CACHE_LIFETIME_SECONDS);

            return $data;
        }

        $data = json_decode($cachedData, true);

        if (!is_array($data)) {
            $this->_logger->warning('Ignoring malformed Postcode.eu status cache payload.');

            return [];
        }

        $accountInfo = $data['accountInfo'] ?? null;
        $moduleInfo = $data['moduleInfo'] ?? null;

        if (!is_array($accountInfo) || !is_array($moduleInfo)) {
            $this->_logger->warning('Postcode.eu status cache payload is missing account/module info.');
        }

        return [
            'accountInfo' => is_array($accountInfo) ? $accountInfo : [],
            'moduleInfo' => is_array($moduleInfo) ? $moduleInfo : [],
        ];
    }

    /**
     * Get Postcode.eu API account info.
     *
     * @return array
     */
    private function _getAccountInfo(): array
    {
        $status = $this->_storeConfigHelper->getValue('account_status');
        if ($status === ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE) {
            return $this->_apiClientHelper->getAccountInfo();
        }

        return [];
    }

    /**
     * Set a notification if an update is available.
     */
    private function _notifyUpdate(): void
    {
        $moduleInfo = $this->getModuleInfo();

        if (!($moduleInfo['has_update'] ?? false)) {
            return;
        }

        if (!isset($moduleInfo['latest_version'])) {
            $this->_logger->warning('Module update flagged without a latest_version; skipping notification.');

            return;
        }

        $this->_updateNotifier->notifyVersion($moduleInfo['latest_version']);
    }
}
