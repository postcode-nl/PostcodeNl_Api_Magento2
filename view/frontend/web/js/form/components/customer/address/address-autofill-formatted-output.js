define([
    'Flekto_Postcode/js/form/components/address-autofill-formatted-output',
], function (Html) {
    'use strict';

    return Html.extend({
        defaults: {
            imports: {
                countryCode: '${$.parentName}:countryCode',
                onChangeCountry: '${$.parentName}:countryCode',
            },
        },
    });
});
