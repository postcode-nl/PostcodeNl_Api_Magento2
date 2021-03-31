<?php

namespace Flekto\Postcode\Controller\Adminhtml\System\Config;

use Flekto\Postcode\Helper\PostcodeApiClient;
use \Magento\Backend\App\Action\Context;
use \Magento\Framework\Controller\Result\JsonFactory;

class Apicheck extends \Magento\Framework\App\Action\Action
{
    const API_URL = 'https://api.postcode.eu';
    const API_TIMEOUT = 3;


    /**
     * resultJsonFactory
     *
     * @var mixed
     * @access protected
     */
    protected $resultJsonFactory;


    /**
     * __construct function.
     *
     * @access public
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @return void
     */
    public function __construct(Context $context, JsonFactory $resultJsonFactory)
    {
        $this->resultJsonFactory = $resultJsonFactory;

        parent::__construct($context);
    }


    /**
     * execute function.
     *
     * @access public
     * @return void
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $newApiKey = $params['apikey'];
        $newApiSecret = $params['apisecret'];

        $access = $this->checkApiKey($newApiKey, $newApiSecret);

        if (empty($access) || !is_array($access)) {
            $result['message'] = __('Key or secret are invalid');
            $result['key_is_valid'] = 'no';

        } else {

            if ($access['hasAccess'] == 1) {

                $result['message'] = __("Your account is active with name: ") . $access['name'];
                $result['supported_countries'] = implode(', ', $access['countries']);
                $result['account_name'] = $access['name'];
                $result['key_is_valid'] = 'yes';

            } else {

                $result['message'] = __("Key is valid but has no access");
                $result['key_is_valid'] = 'no';

            }
        }

        return $this->resultJsonFactory->create()->setData($result);
    }


    /**
     * checkApiKey function.
     *
     * @access private
     * @param mixed $newApiKey
     * @param mixed $newApiSecret
     * @return void
     */
    private function checkApiKey($newApiKey, $newApiSecret)
    {
        try {
            $client = new PostcodeApiClient($newApiKey, $newApiSecret);
            $info = $client->accountInfo();
        } catch (\Exception $e) {
            return false;
        }

        return $info;
    }
}
