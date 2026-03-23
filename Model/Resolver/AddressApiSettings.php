<?php

namespace PostcodeEu\AddressValidation\Model\Resolver;

use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class AddressApiSettings implements ResolverInterface
{
    /**
     * @var StoreConfigHelper
     */
    protected $_storeConfigHelper;

    /**
     * @param StoreConfigHelper $storeConfigHelper
     */
    public function __construct(
        StoreConfigHelper $storeConfigHelper
    ) {
        $this->_storeConfigHelper = $storeConfigHelper;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        return $this->_storeConfigHelper->getJsinit();
    }
}
