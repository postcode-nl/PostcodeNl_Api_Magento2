<?php

namespace PostcodeEu\AddressValidation\Plugin;

use Magento\Customer\Model\Metadata\Form;
use Magento\Framework\App\RequestInterface;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;

class SortSalesOrderAddressFields
{
    /**
     * @var StoreConfigHelper
     */
    private $_storeConfigHelper;

    /**
     * @var RequestInterface
     */
    private $_request;

    /**
     * @param RequestInterface $request
     * @param StoreConfigHelper $storeConfigHelper
     */
    public function __construct(
        RequestInterface $request,
        StoreConfigHelper $storeConfigHelper
    ) {
        $this->_request = $request;
        $this->_storeConfigHelper = $storeConfigHelper;
    }

    /**
     * Reorder address fields.
     *
     * @param Form $subject
     * @param array $result
     * @return array
     */
    public function afterGetAttributes(Form $subject, array $result)
    {
        if ($this->_storeConfigHelper->isSetFlag('change_fields_position')
            && isset($result['country_id'])
            && strpos($this->_request->getFullActionName(), 'sales_order_create') !== false
        ) {
            $result['country_id']->setSortOrder(70);
            $result['street']->setSortOrder(80);
            $result['postcode']->setSortOrder(90);
            $result['city']->setSortOrder(100);
            $result['region']->setSortOrder(110);
            $result['region_id']->setSortOrder(110);
        }

        return $result;
    }
}
