define([
    'PostcodeEu_AddressValidation/js/form/components/address-autofill-residence',
], function (Abstract) {
    'use strict';

    return Abstract.extend({

        defaults: {
            imports: {
                settings: '${ $.provider }:postcodeEuConfig',
            },
            modules: {
                countrySelect: '${$.parentName}.country_id',
            },
        },

        initialize: function () {
            this._super();

            this.countrySelect((component) => {
                this.onChangeCountry(component.value());
                component.value.subscribe(this.onChangeCountry.bind(this));
            });

            return this;
        },
    });
});
