<?php

namespace PostcodeEu\AddressValidation\Model\Validator;

use Magento\Customer\Model\Customer;
use Magento\Framework\Validator\AbstractValidator;

/**
 * Customer city fields validator.
 */
class City extends AbstractValidator
{
    /**
     * Validate city fields.
     *
     * Adopt pattern from Magento release 2.4.9-alpha3 to fix city validation.
     *
     * @see https://github.com/magento/magento2/issues/39854
     *
     * @param Customer $customer
     * @return bool
     */
    public function isValid($customer): bool
    {
        $city = $customer->getCity();
        if ($city === null) {
            return true;
        }

        if (preg_match('/^[\p{L}\p{M}\d\s\-_\'’\.,&\(\)]{1,100}$/u', $city, $matches)) {
            if ($matches[0] === $city) {
                return true;
            }
        }

        parent::_addMessages([[
            'city' => "Invalid City. Please use letters, numbers, spaces,
            and the following characters: - _ ' ’ . , & ( )"
        ]]);

        return false;
    }
}
