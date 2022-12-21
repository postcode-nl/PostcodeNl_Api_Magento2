/*!
 * Validator mixin for Magento_Ui/js/lib/validation/validator
 */

define([
    'mage/translate',
    'Flekto_Postcode/js/model/address-nl',
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
            function (value, params) { return params.isValid(); },
            $t('Please enter a valid value.') // Customize via params.message property.
        );

        validator.addRule(
            'validate-postcode',
            function (value) { return value === '' || addressNlModel.postcodeRegex.test(value); },
            $t('Please enter a valid zip/postal code.')
        );

        validator.addRule(
            'validate-house-number',
            function (value) { return value === '' || addressNlModel.houseNumberRegex.test(value); },
            $t('Please enter a valid house number.')
        );

        validator.addRule(
            'validate-overseas-territories',
            function (value, params) {
                if (
                    params.component.visible() === false
                    || params.component.address() === null
                    || params.component.countryCode !== 'FR' // Only France for now...
                ) {
                    return true;
                }

                return false === /^9[78]/.test(params.component.address().address.postcode);
            },
            $t('Please select an address from metropolitan France.')
        );

        return validator;
    };
});
