define([
    'Flekto_Postcode/js/form/components/address-autofill-nl',
], function (Collection) {
    'use strict';

    return Collection.extend({
        defaults: {
            imports: {
                fields: '${$.parentName}:fields',
                inputs: '${$.parentName}:inputs',
                countryCode: '${$.parentName}:countryCode',
                isCountryChanged: '${$.parentName}:isCountryChanged',
                onChangeCountry: '${$.parentName}:countryCode',
                settings: '${$.parentName}:settings',
            },
        },

        initialize: function () {
            this._super();

            if (this.countryCode === 'NL') {
                Promise.all([this.prefillPostcode(), this.prefillHouseNumber()])
                    .then(this.getAddress.bind(this))
                    .catch(() => { /* ignore */ });

                this.visible(true);
            }

            return this;
        },

        prefillPostcode: function () {
            return new Promise((resolve, reject) => {
                this.childPostcode((component) => {
                    if (component.value() === '') {
                        component.value(this.inputs.postcode.value);
                    }

                    this.isPostcodeValid() ? resolve() : reject();
                });
            });
        },

        prefillHouseNumber: function () {
            return new Promise((resolve, reject) => {
                this.childHouseNumber((component) => {
                    if (component.value() === '') {
                        const streetAddress = [...this.inputs.street].map((input) => input.value).join(' '),
                            matches = streetAddress.match(/(?<houseNumber>\d+)(?<addition>\D.*)?$/);

                        if (matches !== null) {
                            const { houseNumber = '', addition = '' } = matches.groups;

                            component.value(`${houseNumber} ${addition}`.trim());
                        }
                    }

                    this.isHouseNumberValid() ? resolve() : reject();
                });
            });
        },

        onChangeCountry: function (countryCode) {
            if (this.isCountryChanged) {
                return this._super(countryCode);
            }
        },

        setInputAddress: function (address) {
            const addressParts = this.getAddressParts(address);

            this.inputs.street[0].value = addressParts.street + ' ' + addressParts.house;
            this.inputs.city.value = addressParts.city;
            this.inputs.postcode.value = addressParts.postcode;
            this.inputs.region.value = addressParts.province;
        },

        resetInputAddress: function () {
            this.inputs.toArray().forEach(input => { input.value = ''; });
        },

        toggleFields: function (state) {
            if (this.countryCode !== 'NL') {
                return; // Toggle will be handled by international component.
            }

            switch (this.settings.show_hide_address_fields) {
            case 'disable':
                this.inputs.toArray().forEach(input => { input.disabled = !state; });
                break;
            case 'format':
                if (this.fields.street.style.display === 'none') {
                    return;
                }

                state = false;

            /* falls through */
            case 'hide':
                for (const name of ['street', 'city', 'postcode', 'region']) {
                    this.fields[name].style.display = state ? '' : 'none';
                }
                break;
            }
        },

    });
});
