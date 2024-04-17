define([
    'Flekto_Postcode/js/form/components/address-autofill-formatted-output',
    'uiRegistry',
], function (Html, Registry) {
    'use strict';

    return Html.extend({
        defaults: {
            imports: {
                countryCode: '${$.parentName}.country_id:value',
                onChangeCountry: '${$.parentName}.country_id:value',
            }
        },

        initialize: function () {
            this._super();

            Registry.get(
                [`${this.parentName}.address_autofill_nl`, `${this.parentName}.country_id`],
                this.renderStoredNlAddress.bind(this)
            );

            Registry.get(
                [`${this.parentName}.address_autofill_intl`, `${this.parentName}.country_id`],
                this.renderStoredIntlAddress.bind(this)
            );

            return this;
        },

    });
});
