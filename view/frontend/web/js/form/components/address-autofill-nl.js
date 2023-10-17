define([
    'uiCollection',
    'jquery',
    'Flekto_Postcode/js/model/address-nl',
], function (Collection, $, addressModel) {
    'use strict';

    return Collection.extend({
        defaults: {
            imports: {
                onInputPostcode: '${$.name}.postcode:value',
                onInputHouseNumber: '${$.name}.house_number:value',
                onChangeHouseNumberAddition: '${$.name}.house_number_select:value',
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
        },

        initialize: function () {
            this._super();

            // The "loading" class will be added to the house number element based on loading's observable value.
            // I.e. when looking up an address.
            this.childHouseNumber((component) => component.additionalClasses['loading'] = this.loading);

            this.address.subscribe(this.setInputAddress.bind(this));

            if (this.settings.fixedCountry !== null) {
                this.countryCode = this.settings.fixedCountry;
                this.onChangeCountry();
            }

            return this;
        },

        initElement: function (childInstance) {
            childInstance.visible(this.isNl() && childInstance.index !== 'house_number_select');
        },

        initObservable: function () {
            this._super();
            this.observe('address loading status');
            return this;
        },

        onChangeCountry: function () {
            const isNl = this.isNl();

            this.childPostcode(component => component.visible(isNl));
            this.childHouseNumber(component => component.visible(isNl));
            this.childHouseNumberSelect(component => component.visible(isNl && component.options().length > 0));
            this.toggleFields(!isNl, true);

            if (isNl) {
                this.resetInputAddress();

                // Trigger input handlers in case the fields already have values.
                this.childPostcode(component => this.onInputPostcode(component.value()));
                this.childHouseNumber(component => this.onInputHouseNumber(component.value()));
            }
        },

        isNl: function () {
            return this.countryCode === 'NL';
        },

        onInputPostcode: function (value) {
            clearTimeout(this.lookupTimeout);

            if (!this.childPostcode().visible() || this.childPostcode().checkInvalid()) {
                return;
            }

            this.lookupTimeout = setTimeout(function () {
                if (addressModel.postcodeRegex.test(value)) {
                    if (addressModel.houseNumberRegex.test(this.childHouseNumber().value())) {
                        this.getAddress();
                    }

                    return;
                }

                this.resetHouseNumberSelect();
            }.bind(this), addressModel.lookupDelay);
        },

        onInputHouseNumber: function (value) {
            clearTimeout(this.lookupTimeout);

            if (!this.childHouseNumber().visible() || value === '') {
                this.resetHouseNumberSelect();
                return;
            }

            this.lookupTimeout = setTimeout(function () {
                if (addressModel.houseNumberRegex.test(value)) {
                    if (addressModel.postcodeRegex.test(this.childPostcode().value())) {
                        this.getAddress();
                    }

                    return;
                }

                this.resetHouseNumberSelect();
            }.bind(this), addressModel.lookupDelay);
        },

        getAddress: function () {
            const postcode = addressModel.postcodeRegex.exec(this.childPostcode().value())[0].replace(/\s/g, ''),
                houseNumber = addressModel.houseNumberRegex.exec(this.childHouseNumber().value())[0].trim(),
                url = `${this.settings.base_url}postcode-eu/V1/nl/address/${postcode}/${houseNumber}`;

            this.resetHouseNumberSelect();
            this.resetInputAddress();
            this.loading(true);
            this.childHouseNumber().error(false);

            $.get({
                url: url,
                cache: true,
                dataType: 'json',
                success: function (response) {
                    if (response[0].error) {
                        return this.childHouseNumber().error(response[0].message_details);
                    }

                    this.status(response[0].status);

                    if (this.status() === 'notFound') {
                        return;
                    }

                    this.address(response[0].address);

                    if (this.status() === 'houseNumberAdditionIncorrect') {
                        this.childHouseNumberSelect()
                            .setOptions(response[0].address.houseNumberAdditions)
                            .show();
                    } else {
                        this.toggleFields(true);
                    }
                }.bind(this)
            }).always(this.loading.bind(null, false));
        },

        onChangeHouseNumberAddition: function (value) {
            if (typeof value === 'undefined') {
                this.toggleFields(false);
                this.resetInputAddress();
                return;
            }

            const option = this.childHouseNumberSelect().getOption(value);

            if (typeof option !== 'undefined' && typeof option.houseNumberAddition !== 'undefined') {
                this.address().houseNumberAddition = option.houseNumberAddition;
                this.status('valid');
                this.address.valueHasMutated();
                this.toggleFields(true);
            }
        },

        resetHouseNumberSelect: function () {
            this.childHouseNumberSelect(component => component.setOptions([]).hide());
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
