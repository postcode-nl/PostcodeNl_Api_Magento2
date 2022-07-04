define([
    'Flekto_Postcode/js/form/element/address-autofill-field',
], function (autofillField) {
    'use strict';

    return autofillField.extend({

        defaults: {
            validation: {
                'validate-house-number': true,
            },
        },

    });
});
