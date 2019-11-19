<?php

namespace Flekto\Postcode\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Flekto\Postcode\Helper\ApiClientHelper;
use Magento\Customer\Model\Session;

/**
 * Class CustomConfigProvider
 */
class CustomConfigProvider implements ConfigProviderInterface
{

    /**
     * apiClientHelper
     *
     * @var mixed
     * @access protected
     */
    protected $apiClientHelper;


    /**
     * customerSession
     *
     * @var mixed
     * @access protected
     */
    protected $customerSession;


    /**
     * __construct function.
     *
     * @access public
     * @param ApiClientHelper $apiClientHelper
     * @param Session $customerSession
     * @return void
     */
    public function __construct(ApiClientHelper $apiClientHelper, Session $customerSession)
    {
        $this->apiClientHelper = $apiClientHelper;
        $this->customerSession = $customerSession;
    }


    /**
     * getConfig function.
     *
     * @access public
     * @return void
     */
    public function getConfig()
    {
        $config = [
            'flekto_postcode' => [
                'settings' => $this->apiClientHelper->getJsinit(false)
            ]
        ];
        return $config;
    }
}
