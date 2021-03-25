define([
    'uiCollection',
    'ko',
    'jquery',
    'mage/translate',
], function (Collection, ko, $, $t) {
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
            lookupDelay: 750,
            postcodeRegex: /[1-9][0-9]{3}\s*[a-z]{2}/i,
            houseNumberRegex: /[1-9]\d{0,4}(?:\D.*)?$/i,
        },

        settings: window.checkoutConfig.flekto_postcode.settings,
        isComponentEnabled: false,
        lookupTimeout: null,
        address: null,
        loading: ko.observable(false),

		initialize: function () {
			this._super();

            if (this.settings.enabled) {
                this.isComponentEnabled = this.settings.nl_input_behavior === 'zip_house';
            }

            if (this.isComponentEnabled) {
                // Toggle fields after street component is loaded.
                this.street(this.toggleFields.bind(this, false));

                // The "loading" class will be added to the house number element based on loading's observable value.
                // I.e. when looking up an address.
                this.childHouseNumber(function (component) {
                    component.additionalClasses['loading'] = this.loading;
                }.bind(this));
            }

			return this;
		},

        initElement: function (childInstance) {
            if (!this.isComponentEnabled) {
                childInstance.hide();
                childInstance.destroy();
                return;
            }

            childInstance.visible(this.isNl() && childInstance.index !== 'house_number_select');
        },

        onChangeCountry: function () {
            if (!this.isComponentEnabled) {
                return;
            }

            const isNl = this.isNl();

            this.childPostcode().visible(isNl);
            this.childHouseNumber().visible(isNl);
            this.childHouseNumberSelect().visible(isNl && this.childHouseNumberSelect().options().length > 0);
            this.toggleFields(!isNl);
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
                if (this.postcodeRegex.test(value)) {
                    if (this.houseNumberRegex.test(this.childHouseNumber().value())) {
                        this.getAddress();
                    }

                    return;
                }

                this.childPostcode().error($t('Please enter a valid zip/postal code.'));
                this.resetHouseNumberSelect();
            }.bind(this), this.lookupDelay);
        },

        onInputHouseNumber: function (value) {
            clearTimeout(this.lookupTimeout);

            if (value === '') {
                this.resetHouseNumberSelect();
                return this.childHouseNumber().error(false);
            }

            this.lookupTimeout = setTimeout(function () {
                if (this.houseNumberRegex.test(value)) {
                    if (this.postcodeRegex.test(this.childPostcode().value())) {
                        this.getAddress();
                    }

                    return;
                }

                this.childHouseNumber().error($t('Please enter a valid house number.'));
                this.resetHouseNumberSelect();
            }.bind(this), this.lookupDelay);
        },

        getAddress: function () {
            const postcode = this.postcodeRegex.exec(this.childPostcode().value())[0].replace(/\s/g, ''),
                houseNumber = this.houseNumberRegex.exec(this.childHouseNumber().value())[0].trim();

            this.resetHouseNumberSelect();
            this.resetInputAddress();
            this.loading(true);

            const url = this.settings.base_url + 'rest/V1/flekto/postcode-international/nlzipcode/' + postcode + '/' + houseNumber;

            $.get(url, function (response) {
                if (response[0].error) {
                    return this.childHouseNumber().error(response[0].message_details);
                }

                if (response[0].status === 'notFound') {
                    return this.childHouseNumber().error($t('Address not found.'));
                }

                this.address = response[0].address;

                if (response[0].status === 'houseNumberAdditionIncorrect') {
                    this.childHouseNumberSelect()
                        .setOptions(this.address.houseNumberAdditions)
                        .show();
                }
                else {
                    this.setInputAddress(this.address);
                    this.toggleFields(true);
                }
            }.bind(this)).always(this.loading.bind(null, false));
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
                return this.resetInputAddress();
            }

            const option = this.childHouseNumberSelect().getOption(value);

            if (typeof option.houseNumberAddition !== 'undefined') {
                this.address.houseNumberAddition = option.houseNumberAddition;
                this.setInputAddress(this.address);
                this.toggleFields(true);
            }
        },

        resetInputAddress: function () {
            this.street().elems.each(function (streetInput) { streetInput.reset(); });
            this.city().reset();
            this.postcode().reset();
            this.regionIdInput().reset();
        },

        resetHouseNumberSelect: function () {
            this.childHouseNumberSelect().setOptions([]).hide();
        },

        toggleFields: function (state) {
            if (!this.isNl()) {
                // Always re-enable region. This is not needed for .visible() because the region field has its own logic for that.
                this.regionIdInput(function (component) { component.enable() });
                return;
            }

            if (this.settings.show_hide_address_fields === 'disable') {
                const fields = ['city', 'postcode', 'regionIdInput'];

                for (let i in fields) {
                    this[fields[i]](function (component) { component.disabled(!state) });
                }

                this.street(function (component) {
                    component.elems.each(function (streetInput) { streetInput.disabled(!state) });
                });
            }
            else if (this.settings.show_hide_address_fields === 'hide') {
                const fields = ['street', 'city', 'postcode'];

                for (let i in fields) {
                    this[fields[i]](function (component) { component.visible(state) });
                }

                this.regionIdInput(function (component) { component.visible(state) });
            }
        },
    });
});
