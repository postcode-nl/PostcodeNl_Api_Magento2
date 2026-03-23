<?php

namespace PostcodeEu\AddressValidation\Model\Resolver\IntlAddress;

use PostcodeEu\AddressValidation\Model\Resolver\IntlAddress;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class Details extends IntlAddress implements ResolverInterface
{
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
        $this->requireSessionHeader();

        $result = $this->_apiClientHelper->getAddressDetails($args['context']);
        if (isset($result['error'])) {
            throw new GraphQlInputException(__($result['message'] ?? 'Unknown error'));
        }

        return $result;
    }
}
