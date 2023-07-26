<?php

namespace Flekto\Postcode\Block\System\Config;

use Flekto\Postcode\Helper\StoreConfigHelper;
use Flekto\Postcode\Helper\ApiClientHelper;
use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface as CacheTypeList;
use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;

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

    public array $accountInfo = [];

    /**
     * @param Template\Context $context
     * @param StoreConfigHelper $storeConfigHelper
     * @param ApiClientHelper $apiClientHelper
     * @param ConfigInterface $resourceConfig
     * @param CacheTypeList $cacheTypeList
     * @param CacheFrontendPool $cacheFrontendPool
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        StoreConfigHelper $storeConfigHelper,
        ApiClientHelper $apiClientHelper,
        ConfigInterface $resourceConfig,
        CacheTypeList $cacheTypeList,
        CacheFrontendPool $cacheFrontendPool,
        array $data = []
    ) {
        $this->_scopeConfig = $context->getScopeConfig();
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_apiClientHelper = $apiClientHelper;
        $this->_resourceConfig = $resourceConfig;
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheFrontendPool = $cacheFrontendPool;
        $cachedData = $this->_getCachedData();
        $this->accountInfo = $cachedData['accountInfo'];
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
            'enabled' => $this->_storeConfigHelper->isSetFlag(StoreConfigHelper::PATH['enabled']),
            'module_version' => $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['module_version']),
            'supported_countries' => $this->_storeConfigHelper->getSupportedCountries(),
            'account_name' => $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['account_name']),
            'account_status' => $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['account_status']), // Defaults to "new", see etc/config.xml.
            'has_credentials' => $this->_storeConfigHelper->hasCredentials(),
        ];
    }

    /**
     * Get short description of API status.
     *
     * @return string
     */
    public function getApiStatusDescription(): string
    {
        $status = $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['account_status']);

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
     * Get Postcode.eu API account info.
     *
     * @return array
     */
    private function _getAccountInfo(): array
    {
        $status = $this->_storeConfigHelper->getValue(StoreConfigHelper::PATH['account_status']);
        if ($status === \Flekto\Postcode\Helper\ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE)
            return $this->_apiClientHelper->getApiClient()->accountInfo();

        return [];
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

        if ($cachedData === false)
        {
            $data = [];
            $data['accountInfo'] = $this->_getAccountInfo();
            $cache->save(serialize($data), self::CACHE_ID, [], self::CACHE_LIFETIME_SECONDS);
            return $data;
        }

        return unserialize($cachedData);
    }
}
