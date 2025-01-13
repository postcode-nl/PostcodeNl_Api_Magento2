<?php

namespace Flekto\Postcode\Block\System\Config;

use Flekto\Postcode\Helper\StoreConfigHelper;
use Flekto\Postcode\Helper\ApiClientHelper;
use Flekto\Postcode\Helper\Data as DataHelper;
use Flekto\Postcode\Model\UpdateNotification\UpdateNotifier;
use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface as CacheTypeList;
use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;
use Magento\Framework\Serialize\SerializerInterface;

class Status extends Template implements RendererInterface
{
    public const CACHE_ID = 'postcode-eu-status';
    public const CACHE_LIFETIME_SECONDS = 3600;

    protected $_template = 'Flekto_Postcode::system/config/status.phtml';
    protected $_scopeConfig;
    protected $_storeConfigHelper;
    protected $_apiClientHelper;
    protected $_resourceConfig;
    protected $_cacheTypeList;
    protected $_cacheFrontendPool;
    protected $_serializer;
    protected $_dataHelper;
    protected $_updateNotifier;

    private $_cachedData;

    public array $accountInfo;
    public array $moduleInfo;

    /**
     * @param Template\Context $context
     * @param StoreConfigHelper $storeConfigHelper
     * @param ApiClientHelper $apiClientHelper
     * @param ConfigInterface $resourceConfig
     * @param CacheTypeList $cacheTypeList
     * @param CacheFrontendPool $cacheFrontendPool
     * @param SerializerInterface $serializer
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
        SerializerInterface $serializer,
        DataHelper $dataHelper,
        UpdateNotifier $updateNotifier,
        array $data = []
    ) {
        $this->_scopeConfig = $context->getScopeConfig();
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_apiClientHelper = $apiClientHelper;
        $this->_resourceConfig = $resourceConfig;
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheFrontendPool = $cacheFrontendPool;
        $this->_serializer = $serializer;
        $this->_dataHelper = $dataHelper;
        $this->_updateNotifier = $updateNotifier;

        $this->_cachedData = $this->_getCachedData();

        $this->_notifyUpdate();

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
            'supported_countries' => $this->_storeConfigHelper->getSupportedCountries(),
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
            case \Flekto\Postcode\Helper\ApiClientHelper::API_ACCOUNT_STATUS_NEW:
                return __('not connected');
            case \Flekto\Postcode\Helper\ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE:
                return __('active');
            case \Flekto\Postcode\Helper\ApiClientHelper::API_ACCOUNT_STATUS_INVALID_CREDENTIALS:
                return __('invalid key and/or secret');
            case \Flekto\Postcode\Helper\ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE:
                return __('inactive');
            default:
                throw new Status\Exception(__('Invalid account status value.'));
        }
    }

    /**
     * Get cached data.
     *
     * @return array
     */
    private function _getCachedData(): array
    {
        $cache = $this->_cacheFrontendPool->get(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
        $cachedData = $cache->load(self::CACHE_ID);

        if ($cachedData === false) {
            $data = [];
            $data['accountInfo'] = $this->_getAccountInfo();
            $data['moduleInfo'] = $this->_dataHelper->getModuleInfo();
            $cache->save($this->_serializer->serialize($data), self::CACHE_ID, [], self::CACHE_LIFETIME_SECONDS);
            return $data;
        }

        return $this->_serializer->unserialize($cachedData);
    }

    /**
     * Get Postcode.eu API account info.
     *
     * @return array
     */
    private function _getAccountInfo(): array
    {
        $status = $this->_storeConfigHelper->getValue('account_status');
        if ($status === \Flekto\Postcode\Helper\ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE) {
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
        if ($moduleInfo['has_update'] ?? false) {
            $this->_updateNotifier->notifyVersion($moduleInfo['latest_version']);
        }
    }
}
