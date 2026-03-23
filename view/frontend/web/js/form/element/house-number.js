define([
    'PostcodeEu_AddressValidation/js/form/element/address-autofill-field',
    'PostcodeEu_AddressValidation/js/model/address-nl',
    'mage/translate',
], function (autofillField, AddressNlModel, $t) {
    'use strict';

    return autofillField.extend({

        defaults: {
            addressStatus: null,
            validation: {
                'validate-house-number': true,
            },
            imports: {
                onStatus: '${ $.parentName }:status',
            },
            additionalClasses: {
                'address-autofill-nl-house-number': true,
            },
        },

        initialize: function () {
            this._super();

            let validateCallbackMessage;

            this.validation['validate-callback'] = {
                isValid: () => {
                    if (this.addressStatus === AddressNlModel.status.NOT_FOUND) {
                        validateCallbackMessage = $t('Address not found.');
                        return false;
                    }
                    else if (this.addressStatus === AddressNlModel.status.PO_BOX_SHIPPING_NOT_ALLOWED) {
                        validateCallbackMessage = $t('Sorry, we cannot ship to a PO Box address.');
                        return false;
                    }

                    return true;
                },
                message: () => validateCallbackMessage,
            };

            return this;
        },

        onStatus: function (status) {
            this.addressStatus = status;
            this.validate();
        },

    });
});
