define([
    'Flekto_Postcode/js/form/element/address-autofill-field',
    'mage/translate',
], function (autofillField, $t) {
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
        },

        initialize: function () {
            this._super();
            this.validation['validate-callback'] = {
                isValid: () => this.addressStatus !== 'notFound',
                message: $t('Address not found.'),
            };
            return this;
        },

        onStatus: function (status) {
            this.addressStatus = status;
            this.validate();
        },

    });
});
