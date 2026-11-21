define([
    'PostcodeEu_AddressValidation/js/form/components/address-autofill-residence',
], function (Abstract) {
    'use strict';

    return Abstract.extend({
        defaults: {
            imports: {
                settings: '${$.parentName}:settings',
                onChangeCountry: '${$.parentName}:countryCode',
            },
        },
    });
});
