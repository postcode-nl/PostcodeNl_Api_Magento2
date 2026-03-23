/*!
 * Validator mixin for Magento_Ui/js/lib/validation/validator
 */

define([
    'mage/translate',
    'PostcodeEu_AddressValidation/js/model/address-nl',
], function ($t, addressNlModel) {
    'use strict';

    return function (validator) {

        /**
         * Add validator rule that simply calls isValid() on the params object.
         * This allows validation logic to be implemented in UI components.
         */
        validator.addRule(
            'validate-callback',

            /**
             * @param value - Current element value (not used here).
             * @param {Object} params - Object with isValid() method.
             * @return {boolean} - Valid if true.
             */
            (_, params) => params.isValid(),
            $t('Please enter a valid value.') // Customize via params.message property.
        );

        validator.addRule(
            'validate-postcode',
            (value) => value === '' || addressNlModel.postcodeRegex.test(value),
            $t('Please enter a valid zip/postal code.')
        );

        validator.addRule(
            'validate-house-number',
            (value) => value === '' || addressNlModel.houseNumberRegex.test(value),
            $t('Please enter a valid house number.')
        );

        return validator;
    };
});
