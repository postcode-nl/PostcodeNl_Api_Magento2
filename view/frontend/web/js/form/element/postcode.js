define([
    'Flekto_Postcode/js/form/element/address-autofill-field',
], function (autofillField) {
    'use strict';

    return autofillField.extend({

        defaults: {
            validation: {
                'validate-postcode': true,
            },
            placeholder: '1234 AB',
            additionalClasses: {
                'address-autofill-nl-postcode': true,
            },
        },

    });
});
