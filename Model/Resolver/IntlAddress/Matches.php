<?php

namespace PostcodeEu\AddressValidation\Model\Resolver\IntlAddress;

use PostcodeEu\AddressValidation\Model\Resolver\IntlAddress;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class Matches extends IntlAddress implements ResolverInterface
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

        $result = $this->_apiClientHelper->getAddressAutocomplete($args['context'], $args['term']);
        if (isset($result['error'])) {
            throw new GraphQlInputException(__($result['message'] ?? 'Unknown error'));
        }

        if (count($result['matches']) > 0) {
            foreach ($result['matches'] as &$match) {
                foreach ($match['highlights'] as &$hl) {
                    $hl['start'] =& $hl[0];
                    $hl['end'] =& $hl[1];
                }
            }
        }

        return $result;
    }
}
