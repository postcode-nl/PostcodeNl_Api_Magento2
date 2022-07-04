define([
    'uiCollection',
    'uiRegistry',
    'ko',
    'jquery',
    'mage/translate',
    'Flekto_Postcode/js/model/address-nl',
], function (Collection, Registry, ko, $, $t, addressModel) {
    'use strict';

    return Collection.extend({
        defaults: {
            imports: {
                countryCode: '${$.parentName}.country_id:value',
                postcodeValue: '${$.name}.postcode:value',
                houseNumberValue: '${$.name}.house_number:value',
                onChangeCountry: '${$.parentName}.country_id:value',
                onInputPostcode: '${$.name}.postcode:value',
                onInputHouseNumber: '${$.name}.house_number:value',
                onChangeHouseNumberAddition: '${$.name}.house_number_select:value',
            },
            modules: {
                street: '${$.parentName}.street',
                city: '${$.parentName}.city',
                postcode: '${$.parentName}.postcode',
                regionIdInput: '${$.parentName}.region_id_input',
                childPostcode: '${$.name}.postcode',
                childHouseNumber: '${$.name}.house_number',
                childHouseNumberSelect: '${$.name}.house_number_select',
            },
            settings: window.checkoutConfig.flekto_postcode.settings,
            address: null,
            lookupTimeout: null,
            loading: false,
            status: null,
            addressFields: null,
        },

        initialize: function () {
            this._super();

            this.addressFields = Registry.async([
                this.parentName + '.street',
                this.parentName + '.city',
                this.parentName + '.postcode',
                this.parentName + '.region_id_input',
            ]),

            // The "loading" class will be added to the house number element based on loading's observable value.
            // I.e. when looking up an address.
            this.childHouseNumber(function (component) {
                component.additionalClasses['loading'] = this.loading;
            }.bind(this));

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
            this.addressFields(function () { // Wait for address fields to be available.
                const isNl = this.isNl();

                this.childPostcode().visible(isNl);
                this.childHouseNumber().visible(isNl);
                this.childHouseNumberSelect().visible(isNl && this.childHouseNumberSelect().options().length > 0);
                this.toggleFields(!isNl, true);

                if (isNl) {
                    this.resetInputAddress();
                }
            }.bind(this));
        },

        isNl: function () {
            return this.countryCode === 'NL';
        },

        onInputPostcode: function (value) {
            clearTimeout(this.lookupTimeout);

            if (value === '') {
                return this.childPostcode().error(false)
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

            if (value === '') {
                this.resetHouseNumberSelect();
                return this.childHouseNumber().error(false);
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
                houseNumber = addressModel.houseNumberRegex.exec(this.childHouseNumber().value())[0].trim();

            this.resetHouseNumberSelect();
            this.resetInputAddress();
            this.loading(true);

            const url = this.settings.base_url + 'postcode-eu/V1/nl/address/' + postcode + '/' + houseNumber;

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
                        return this.childHouseNumber().error($t('Address not found.'));
                    }

                    this.address(response[0].address);

                    if (this.status() === 'houseNumberAdditionIncorrect') {
                        this.childHouseNumberSelect()
                            .setOptions(response[0].address.houseNumberAdditions)
                            .show();
                    }
                    else {
                        this.toggleFields(true);
                    }
                }.bind(this)
            }).always(this.loading.bind(null, false));
        },

        setInputAddress: function (address) {
            const streetInputs = this.street().elems(),
                addition = address.houseNumberAddition ? ' ' + address.houseNumberAddition : '';

            if (streetInputs.length > 2) {
                streetInputs[0].value(address.street);
                streetInputs[1].value(String(address.houseNumber));
                streetInputs[2].value(addition.trim());
            }
            else if (streetInputs.length > 1) {
                streetInputs[0].value(address.street);
                streetInputs[1].value(address.houseNumber + addition);
            }
            else {
                streetInputs[0].value(address.street + ' ' + address.houseNumber + addition);
            }

            this.city().value(address.city);
            this.postcode().value(address.postcode);
            this.regionIdInput().value(address.province);
        },

        onChangeHouseNumberAddition: function (value) {
            if (typeof value === 'undefined') {
                this.toggleFields(false);
                this.resetInputAddress();
                return;
            }

            const option = this.childHouseNumberSelect().getOption(value);

            if (typeof option.houseNumberAddition !== 'undefined') {
                this.address().houseNumberAddition = option.houseNumberAddition;
                this.status('valid');
                this.address.valueHasMutated();
                this.toggleFields(true);
            }
        },

        resetInputAddress: function () {
            this.street().elems.each(function (streetInput) { streetInput.reset(); });
            this.city().reset();
            this.postcode().reset();
            this.regionIdInput().reset();
            this.status(null);
        },

        resetHouseNumberSelect: function () {
            this.childHouseNumberSelect().setOptions([]).hide();
        },

        toggleFields: function (state, force) {
            if (!this.isNl()) {
                // Always re-enable region. This is not needed for .visible() because the region field has its own logic for that.
                this.regionIdInput(function (component) { component.enable() });
                return;
            }

            switch (this.settings.show_hide_address_fields) {
                case 'disable':
                    {
                        const fields = ['city', 'postcode', 'regionIdInput'];

                        for (let i = 0, field; field = fields[i++];) {
                            this[field](function (component) { component.disabled(!state) });
                        }

                        let j = 4;

                        while (j--) {
                            Registry.async(this.street().name + '.' + j)('disabled', !state);
                        }
                    }
                break;
                case 'format':
                    if (!force)
                    {
                        if (!this.street().visible()) {
                            return;
                        }

                        state = false;
                    }
                    /* falls through */
                case 'hide':
                    {
                        const fields = ['street', 'city', 'postcode'];

                        for (let i = 0, field; field = fields[i++];) {
                            this[field](function (component) { component.visible(state) });
                        }

                        this.regionIdInput(function (component) { component.visible(state) });
                    }
                break;
            }
        },

    });
});
