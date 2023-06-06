<?php

namespace Flekto\Postcode\Block\System\Config;

use Flekto\Postcode\Helper\StoreConfigHelper;
use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;

class Status extends Template implements RendererInterface
{
    protected $_template = 'Flekto_Postcode::system/config/status.phtml';
    protected $_scopeConfig;
    protected $_storeConfigHelper;
    protected $_resourceConfig;

    /**
     * @param Template\Context $context
     * @param StoreConfigHelper $storeConfigHelper
     * @param ConfigInterface $resourceConfig
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        StoreConfigHelper $storeConfigHelper,
        ConfigInterface $resourceConfig,
        array $data = []
    ) {
        $this->_scopeConfig = $context->getScopeConfig();
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_resourceConfig = $resourceConfig;
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
}
