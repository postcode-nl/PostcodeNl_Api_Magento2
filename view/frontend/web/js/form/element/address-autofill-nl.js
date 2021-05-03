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
        lookupTimeout: null,
        address: ko.observable(),
        loading: ko.observable(false),
        status: ko.observable(null),

		initialize: function () {
			this._super();

            // Toggle fields after street component is loaded.
            this.street(this.toggleFields.bind(this, false));

            // The "loading" class will be added to the house number element based on loading's observable value.
            // I.e. when looking up an address.
            this.childHouseNumber(function (component) {
                component.additionalClasses['loading'] = this.loading;
            }.bind(this));

            this.address.subscribe(this.setInputAddress.bind(this));

			return this;
		},

        initElement: function (childInstance) {
            childInstance.visible(this.isNl() && childInstance.index !== 'house_number_select');
        },

        onChangeCountry: function () {
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

        toggleFields: function (state) {
            if (!this.isNl()) {
                // Always re-enable region. This is not needed for .visible() because the region field has its own logic for that.
                this.regionIdInput(function (component) { component.enable() });
                return;
            }

            switch (this.settings.show_hide_address_fields)
            {
                case 'disable':
                    {
                        const fields = ['city', 'postcode', 'regionIdInput'];

                        for (let i in fields) {
                            this[fields[i]](function (component) { component.disabled(!state) });
                        }

                        this.street(function (component) {
                            component.elems.each(function (streetInput) { streetInput.disabled(!state) });
                        });
                    }
                break;
                case 'format':
                    if (!this.street().visible()) {
                        return;
                    }

                    state = false;
                // Fallthrough
                case 'hide':
                    {
                        const fields = ['street', 'city', 'postcode'];

                        for (let i in fields) {
                            this[fields[i]](function (component) { component.visible(state) });
                        }

                        this.regionIdInput(function (component) { component.visible(state) });
                    }
                break;
            }
        },
    });
});
