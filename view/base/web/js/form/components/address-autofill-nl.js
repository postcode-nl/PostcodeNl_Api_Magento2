define([
    'uiCollection',
    'jquery',
    'PostcodeEu_AddressValidation/js/model/address-nl',
], function (Collection, $, AddressNlModel) {
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
            inputs: null,
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
            this.toggleFields(isVisible && this.status() === AddressNlModel.status.VALID);
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

        onInputPostcode: function () {
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
                if (this.isPostcodeValid() && this.isHouseNumberValid()) {
                    this.getAddress();
                }
            }, AddressNlModel.lookupDelay);
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
                if (this.isHouseNumberValid() && this.isPostcodeValid()) {
                    this.getAddress();
                }
            }, AddressNlModel.lookupDelay);
        },

        isPostcodeValid: function () {
            return AddressNlModel.postcodeRegex.test(this.childPostcode().value());
        },

        isHouseNumberValid: function () {
            return AddressNlModel.houseNumberRegex.test(this.childHouseNumber().value());
        },

        getAddress: function () {
            const postcode = encodeURIComponent(
                    AddressNlModel.postcodeRegex.exec(this.childPostcode().value())[0].replace(/\s/g, '')
                ),
                houseNumber = encodeURIComponent(
                    AddressNlModel.houseNumberRegex.exec(this.childHouseNumber().value())[0].trim()
                ),
                url = `${this.settings.api_actions.dutchAddressLookup}/${postcode}/${houseNumber}`;

            this.resetInputAddress();
            this.address(null);
            this.status(null);
            this.loading(true);
            this.childHouseNumber().error(false);

            $.get({
                url: url,
                cache: true,
                dataType: 'json',
                success: ([response]) => {
                    if (response.error) {
                        return this.childHouseNumber().error(response.message);
                    }

                    this.status(response.status);

                    if (
                        this.status() === AddressNlModel.status.NOT_FOUND
                        || !this.validateAddress(response.address)
                    ) {
                        return;
                    }

                    this.address(response.address);

                    if (this.status() === AddressNlModel.status.ADDITION_INCORRECT) {
                        this.childHouseNumberSelect().setOptions(response.address.houseNumberAdditions);
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
            this.status(isValid ? AddressNlModel.status.VALID : AddressNlModel.status.ADDITION_INCORRECT);
            this.address.valueHasMutated();
            this.toggleFields(isValid);
        },

        resetHouseNumberSelect: function () {
            this.childHouseNumberSelect(component => component.setOptions([]));
        },

        getAddressParts: function (address) {
            return {
                ...address,
                houseNumberAddition: address.houseNumberAddition ?? '',
                house: `${address.houseNumber} ${address.houseNumberAddition ?? ''}`.trim(),
                streetParts: [address.street, address.houseNumber, address.houseNumberAddition ?? ''],
            };
        },

        setInputAddress: function (address) {
            if (this.inputs === null) {
                return;
            }

            const addressParts = this.getAddressParts(address),
                setValue = (input, value) => {
                    input.value = value;
                    input.dispatchEvent(new Event('change', {bubbles: true}));
                };

            let streetLines;

            if (this.settings.split_street_values) {
                const lastChildIndex = this.inputs.street.length - 1;

                streetLines = addressParts.streetParts.slice(0, lastChildIndex);
                streetLines.push(addressParts.streetParts.slice(lastChildIndex).join(' ').trim());
            } else {
                streetLines = [addressParts.streetParts.join(' ').trim()];
            }

            for (let i = 0; i < streetLines.length; i++) {
                setValue(this.inputs.street[i], streetLines[i]);
            }

            setValue(this.inputs.city, addressParts.city);
            setValue(this.inputs.postcode, addressParts.postcode);

            if (this.inputs.region) {
                setValue(this.inputs.region, addressParts.province);
            }
        },

    });
});
