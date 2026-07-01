<?php

namespace PostcodeEu\AddressValidation\Controller\Adminhtml\Address;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\ServiceOutputProcessor;
use PostcodeEu\AddressValidation\Api\PostcodeModelInterface;

class Api extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'PostcodeEu_AddressValidation::config_postcode_eu';

    /** @var JsonFactory */
    protected $_resultJsonFactory;
    /** @var PostcodeModelInterface */
    protected $_postcodeModel;
    /** @var ServiceOutputProcessor */
    protected $_serviceOutputProcessor;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        PostcodeModelInterface $postcodeModel,
        ServiceOutputProcessor $serviceOutputProcessor
    ) {
        parent::__construct($context);
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_postcodeModel = $postcodeModel;
        $this->_serviceOutputProcessor = $serviceOutputProcessor;
    }

    /**
     * Call address API methods
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->_resultJsonFactory->create();
        $request = $this->getRequest();

        try {
            switch ($request->getParam('method')) {
                case 'postcode':
                    $serviceMethod = 'getNlAddress';
                    $required = ['postcode', 'house_number'];
                    break;
                case 'autocomplete':
                    $serviceMethod = 'getAddressAutocomplete';
                    $required = ['context', 'term'];
                    break;
                case 'address_details':
                    $serviceMethod = 'getAddressDetails';
                    $required = ['context'];
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid service method');
            }

            $values = [];

            foreach ($required as $param) {
                $value = $request->getParam($param) ?? '';

                if (!is_scalar($value)) {
                    throw new \InvalidArgumentException(sprintf('Invalid parameter `%s`', $param));
                }

                if (trim($value) === '') {
                    throw new \InvalidArgumentException(sprintf('Missing parameter `%s`', $param));
                }

                $values[] = $value;
            }

            $result = $this->_postcodeModel->$serviceMethod(...$values);
            $result = $this->_serviceOutputProcessor->process($result, PostcodeModelInterface::class, $serviceMethod);

            return $resultJson->setData($result);
        } catch (\Throwable $e) {
            return $resultJson->setHttpResponseCode(400)->setData(['error' => $e->getMessage()]);
        }
    }
}
