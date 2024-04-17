define([
    'uiCollection',
    'jquery',
    'Flekto_Postcode/js/model/address-nl',
], function (Collection, $, addressModel) {
    'use strict';

    return Collection.extend({
        defaults: {
            listens: {
                '${$.name}.postcode:value': 'onInputPostcode',
                '${$.name}.house_number:value': 'onInputHouseNumber',
                '${$.name}.house_number_select:value': 'onChangeHouseNumberAddition',
                visible: 'onVisible',
            },
            modules: {
                childPostcode: '${$.name}.postcode',
                childHouseNumber: '${$.name}.house_number',
                childHouseNumberSelect: '${$.name}.house_number_select',
            },
            address: null,
            lookupTimeout: null,
            loading: false,
            status: null,
            settings: {},
            visible: false,
        },

        initialize: function () {
            this._super();

            // The "loading" class will be added to the house number element based on loading's observable value.
            // I.e. when looking up an address.
            this.childHouseNumber((component) => { component.additionalClasses['loading'] = this.loading; });

            this.address.subscribe((address) => {
                if (address !== null) {
                    this.setInputAddress(address);
                }
            });

            return this;
        },

        initObservable: function () {
            this._super();
            this.observe('address loading status visible');
            return this;
        },

        onVisible: function (isVisible) {
            this.toggleFields(isVisible && this.status() === 'valid');
        },

        onChangeCountry: function (countryCode) {
            if (countryCode !== 'NL') {
                this.visible(false);
                return;
            }

            if (this.address() !== null) {
                this.setInputAddress(this.address());
            } else {
                this.resetInputAddress();
            }

            this.visible(true);
        },

        onInputPostcode: function (value) {
            clearTimeout(this.lookupTimeout);

            if (
                !this.childPostcode().valueChangedByUser
                || !this.childPostcode().visible()
                || this.childPostcode().checkInvalid() !== null
            ) {
                return;
            }

            this.resetHouseNumberSelect();

            this.lookupTimeout = setTimeout(() => {
                if (
                    addressModel.postcodeRegex.test(value)
                    && addressModel.houseNumberRegex.test(this.childHouseNumber().value())
                ) {
                    this.getAddress();
                }
            }, addressModel.lookupDelay);
        },

        onInputHouseNumber: function (value) {
            clearTimeout(this.lookupTimeout);

            if (
                !this.childHouseNumber().valueChangedByUser
                || !this.childHouseNumber().visible()
                || value === ''
            ) {
                return;
            }

            this.resetHouseNumberSelect();

            this.lookupTimeout = setTimeout(() => {
                if (
                    addressModel.houseNumberRegex.test(value)
                    && addressModel.postcodeRegex.test(this.childPostcode().value())
                ) {
                    this.getAddress();
                }
            }, addressModel.lookupDelay);
        },

        getAddress: function () {
            const postcode = addressModel.postcodeRegex.exec(this.childPostcode().value())[0].replace(/\s/g, ''),
                houseNumber = addressModel.houseNumberRegex.exec(this.childHouseNumber().value())[0].trim(),
                url = `${this.settings.base_url}postcode-eu/V1/nl/address/${postcode}/${houseNumber}`;

            this.resetInputAddress();
            this.address(null);
            this.status(null);
            this.loading(true);
            this.childHouseNumber().error(false);

            $.get({
                url: url,
                cache: true,
                dataType: 'json',
                success: (response) => {
                    if (response[0].error) {
                        return this.childHouseNumber().error(response[0].message_details);
                    }

                    this.status(response[0].status);

                    if (this.status() === 'notFound' || !this.validateAddress(response[0].address)) {
                        return;
                    }

                    this.address(response[0].address);

                    if (this.status() === 'houseNumberAdditionIncorrect') {
                        this.childHouseNumberSelect().setOptions(response[0].address.houseNumberAdditions);
                    } else {
                        this.toggleFields(true);
                    }
                }
            }).always(this.loading.bind(this, false));
        },

        validateAddress: function () {
            return true;
        },

        onChangeHouseNumberAddition: function (value) {
            if (!this.childHouseNumberSelect().visible()) {
                return;
            }

            const option = this.childHouseNumberSelect().getOption(value),
                isValid = typeof option !== 'undefined' && typeof option.houseNumberAddition !== 'undefined';

            this.address().houseNumberAddition = isValid ? option.houseNumberAddition : null;
            this.status(isValid ? 'valid' : 'houseNumberAdditionIncorrect');
            this.address.valueHasMutated();
            this.toggleFields(isValid);
        },

        resetHouseNumberSelect: function () {
            this.childHouseNumberSelect(component => component.setOptions([]));
        },

        getAddressParts: function (address) {
            const houseNumber = `${address.houseNumber || ''}`,
                houseNumberAddition = `${address.houseNumberAddition || ''}`.trim();

            return {
                street: address.street,
                house: `${houseNumber} ${houseNumberAddition}`.trim(),
                houseNumber: houseNumber,
                houseNumberAddition: houseNumberAddition,
                postcode: address.postcode,
                city: address.city,
                province: address.province,
            };
        },

    });
});
