define([
    'PostcodeEu_AddressValidation/js/form/components/address-autofill-formatted-output',
], function (Html) {
    'use strict';

    return Html.extend({
        defaults: {
            imports: {
                countryCode: '${$.parentName}:countryCode',
                isCountryChanged: '${$.parentName}:isCountryChanged',
                onChangeCountry: '${$.parentName}:countryCode',
            },
        },

        onChangeCountry: function (countryCode) {
            if (this.isCountryChanged) {
                return this._super(countryCode);
            }
        },

    });
});
