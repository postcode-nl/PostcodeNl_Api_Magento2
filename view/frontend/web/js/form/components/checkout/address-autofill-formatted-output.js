define([
    'PostcodeEu_AddressValidation/js/form/components/address-autofill-formatted-output',
], function (Html) {
    'use strict';

    return Html.extend({
        defaults: {
            imports: {
                countryCode: '${$.parentName}.country_id:value',
            },
            modules: {
                countrySelect: '${$.parentName}.country_id',
            },
        },

        initialize: function () {
            this._super();

            this.countrySelect((component) => {
                component.value.subscribe(this.onChangeCountry.bind(this));
                this.addressAutofillNl(this.renderStoredNlAddress.bind(this));
                this.addressAutofillIntl(this.renderStoredIntlAddress.bind(this));
            });

            return this;
        },

    });
});
