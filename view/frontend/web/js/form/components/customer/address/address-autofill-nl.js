define([
    'PostcodeEu_AddressValidation/js/form/components/address-autofill-nl',
    'PostcodeEu_AddressValidation/js/action/customer/address/get-validated-address',
    'PostcodeEu_AddressValidation/js/model/address-nl',
    'mage/translate',
], function (Collection, getValidatedAddress, AddressNlModel, $t) {
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
                    .catch(() => {
                        if (AddressNlModel.houseNumberRegex.test(this.inputs.getStreetValue())) {
                            // Fall back to Validate API for ambiguous house number cases.
                            // Because when a street line contains multiple numbers, the
                            // house number can't easily be determined via pattern matching.
                            this._getValidatedAddress();
                        }
                    });

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
                        const houseNumberAndAddition = this.extractHouseNumber(this.inputs.getStreetValue());

                        if (houseNumberAndAddition !== null) {
                            component.value(houseNumberAndAddition);
                        }
                    }

                    this.isHouseNumberValid() ? resolve() : reject();
                });
            });
        },

        extractHouseNumber: function (streetAndHouseNumber) {
            const matches = [...streetAndHouseNumber.matchAll(/[1-9]\d{0,4}\D*/g)];

            if (matches[0]?.index === 0) {
                matches.shift(); // Discard leading number as a valid house number.
            }

            if (matches.length === 1) {
                return matches[0][0].trim(); // Single match is most likely the house number.
            }

            return null; // No match or ambiguous (i.e. multiple numbers found).
        },

        _getValidatedAddress: function () {
            const {postcode, city} = this.inputs;

            this.loading(true);
            getValidatedAddress('nl', this.inputs.getStreetValue(), postcode.value, city.value)
                .then((result) => {
                    if (result === null) {
                        this.childHouseNumber().error($t('Address not found.'));
                        return;
                    }

                    const {address} = result;

                    this.childPostcode().value(address.postcode);
                    this.childHouseNumber().value(address.building);
                    this.address({
                        street: address.street,
                        houseNumber: address.buildingNumber,
                        houseNumberAddition: address.buildingNumberAddition,
                        city: address.locality,
                        postcode: address.postcode,
                        province: result.region.name,
                    });
                    this.status(AddressNlModel.status.VALID);
                    this.toggleFields(true);
                })
                .finally(() => {
                    this.loading(false);
                });

            this.resetInputAddress();
        },

        onChangeCountry: function (countryCode) {
            if (this.isCountryChanged) {
                return this._super(countryCode);
            }
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
                    if (this.fields[name]) {
                        this.fields[name].style.display = state ? '' : 'none';
                    }
                }
                break;
            }
        },

    });
});
