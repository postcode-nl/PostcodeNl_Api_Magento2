<?php

namespace PostcodeEu\AddressValidation\Model\Resolver;

use PostcodeEu\AddressValidation\Helper\ApiClientHelper;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class ValidatedAddress implements ResolverInterface
{
    /**
     * @var ApiClientHelper
     */
    protected $_apiClientHelper;

    /**
     * @param ApiClientHelper $apiClientHelper
     */
    public function __construct(
        ApiClientHelper $apiClientHelper
    ) {
        $this->_apiClientHelper = $apiClientHelper;
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
        $result = $this->_apiClientHelper->validateAddress(
            $args['country'],
            $args['postcode'] ?? null,
            $args['locality'] ?? null,
            $args['street'] ?? null,
            $args['building'] ?? null,
            $args['region'] ?? null,
            $args['streetAndBuilding'] ?? null
        );

        if (!empty($result['error'])) {
            throw new GraphQlInputException(__($result['message'] ?? 'Unknown error'));
        }

        return $result;
    }
}
