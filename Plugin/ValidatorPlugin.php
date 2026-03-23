<?php

namespace PostcodeEu\AddressValidation\Plugin;

use Magento\Framework\Validator;
use Magento\Framework\Validator\ValidatorInterface;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * Validator plugin to fix city validation in Magento 2.4.8.
 */
class ValidatorPlugin
{
    private $_productMetadata;

    /**
     * Constructor
     *
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(ProductMetadataInterface $productMetadata)
    {
        $this->_productMetadata = $productMetadata;
    }

    /**
     * Before plugin for the addValidator method
     *
     * @param Validator $subject
     * @param ValidatorInterface $validator
     * @param boolean $breakChainOnFailure
     * @return array|null
     */
    public function beforeAddValidator(
        Validator $subject,
        ValidatorInterface $validator,
        bool $breakChainOnFailure = false
    ): ?array {
        if (substr($this->_productMetadata->getVersion(), 0, 5) === '2.4.8'
            && method_exists($validator, 'getAlias')
            && $validator->getAlias() === 'city_validator'
        ) {
            return [new \PostcodeEu\AddressValidation\Model\Validator\City(), $breakChainOnFailure];
        }

        // Return null if not changing arguments, see
        // https://developer.adobe.com/commerce/php/development/components/plugins/#before-methods
        return null;
    }
}
