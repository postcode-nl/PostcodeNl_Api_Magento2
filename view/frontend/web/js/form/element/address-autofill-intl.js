define([
    'Magento_Ui/js/form/element/abstract',
    'uiRegistry',
    'mage/translate',
    'Flekto_Postcode/js/ko/bindings/init-intl-autocomplete',
], function (Abstract, Registry, $t) {
    'use strict';

    return Abstract.extend({
        defaults: {
            imports: {
                onChangeCountry: '${$.parentName}.country_id:value',
                countryCode: '${$.parentName}.country_id:value',
            },
            modules: {
                street: '${$.parentName}.street',
                city: '${$.parentName}.city',
                postcode: '${$.parentName}.postcode',
            },
            settings: window.checkoutConfig.flekto_postcode.settings,
            loading: false,
            address: null,
            intlAutocompleteInstance: null,
            intlAutocompleteCountries: null,
        },

        initialize: function () {
            this._super();

            if (typeof this.countryCode === 'undefined') {
                this.visible(false);
            }

            if (this.settings.fixedCountry !== null) {
                this.countryCode = this.settings.fixedCountry;

                const fields = [
                    this.parentName + '.street',
                    this.parentName + '.city',
                    this.parentName + '.postcode',
                ];

                // Run country change handler when fields are available.
                Registry.async(fields)(this.onChangeCountry.bind(this, this.countryCode));
            }

            this.additionalClasses['loading'] = this.loading;
            this.address.subscribe(this.setInputAddress.bind(this));

            if (this.settings.show_hide_address_fields !== 'show') {
                this.validation['validate-callback'] = {
                    message: $t('Please enter an address and select it.'),
                    isValid: this.isValid.bind(this),
                };
                this.additionalClasses['required'] = true;
            }

            return this;
        },

        initObservable: function () {
            this._super();
            this.observe('address loading');
            return this;
        },

        onChangeCountry: function (countryCode) {
            if (this.settings.nl_input_behavior === 'zip_house' && countryCode === 'NL') {
                this.visible(false);
                return;
            }

            const isSupported = this.isSupportedCountry(countryCode);

            this.visible(isSupported);
            this.toggleFields(!isSupported, true);

            if (isSupported && this.intlAutocompleteInstance !== null) {
                // Reset address fields on country change.
                this.resetInputAddress();
                this.intlAutocompleteInstance.reset();
                this.intlAutocompleteInstance.setCountry(countryCode);
            }
        },

        isSupportedCountry: function (countryCode) {
            if (this.intlAutocompleteCountries === null) {
                this.intlAutocompleteCountries = JSON.parse(this.settings.supported_countries);
            }

            return this.intlAutocompleteCountries.indexOf(countryCode) > -1;
        },

        setInputAddress: function (result) {
            if (result === null) {
                return;
            }

            const address = result.address,
                streetInputs = this.street().elems(),
                number = String(address.buildingNumber || ''),
                addition = String(address.buildingNumberAddition || '');

            if (streetInputs.length > 2) {
                streetInputs[0].value(address.street);
                streetInputs[1].value(number);
                streetInputs[2].value(addition);
            }
            else if (streetInputs.length > 1) {
                streetInputs[0].value(address.street);
                streetInputs[1].value((number + ' ' + addition).trim());
            }
            else {
                streetInputs[0].value(address.street + ' ' + (number + ' ' + addition).trim());
            }

            this.city().value(address.locality);
            this.postcode().value(address.postcode);
        },

        resetInputAddress: function () {
            this.street().elems.each(function (streetInput) { streetInput.reset(); });
            this.city().reset();
            this.postcode().reset();
            this.address(null);
        },

        toggleFields: function (state, force) {
            switch (this.settings.show_hide_address_fields) {
                case 'disable':
                    let j = 4;

                    while (j--) {
                        Registry.async(this.street().name + '.' + j)('disabled', !state);
                    }

                    this.city(function (component) { component.disabled(!state) });
                    this.postcode(function (component) { component.disabled(!state) });
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
                    const fields = ['street', 'city', 'postcode'];

                    for (let i = 0, field; field = fields[i++];) {
                        this[field](function (component) {
                            component.visible(state)
                        });
                    }
                break;
            }
        },

        isValid: function () {
            return this.visible() === false || this.address() !== null;
        },

    });
});
