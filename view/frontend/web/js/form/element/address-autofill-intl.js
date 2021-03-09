define([
    'Magento_Ui/js/form/element/abstract',
    'ko',
    'Flekto_Postcode/js/lib/postcode-eu-autocomplete-address',
], function (Abstract, ko, AutocompleteAddress) {
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
		},

        settings: window.checkoutConfig.flekto_postcode.settings,
        intlAutocompleteInstance: null,
        intlAutocompleteCountries: null,
        loading: ko.observable(false),

        initialize: function () {
            this._super();

            if (this.settings.enabled) {
                this.bindKoHandler();
                this.additionalClasses['loading'] = this.loading;
            }
            else {
                this.hide();
                this.destroy();
            }

            return this;
        },

        onChangeCountry: function (countryCode) {
            if (!this.settings.enabled) {
                return;
            }

            const isSupported = this.isSupportedCountry(countryCode);

            this.visible(isSupported);
            this.toggleFields(!isSupported);

            if (isSupported && this.intlAutocompleteInstance !== null) {
                this.intlAutocompleteInstance.setCountry(countryCode);
            }
        },

        isSupportedCountry: function (countryCode) {
            if (this.settings.nl_input_behavior === 'zip_house' && countryCode === 'NL') {
                return false;
            }

            if (this.intlAutocompleteCountries === null) {
                this.intlAutocompleteCountries = JSON.parse(this.settings.supported_countries);
            }

            return this.intlAutocompleteCountries.indexOf(countryCode) > -1;
        },

        bindKoHandler: function () {
            ko.bindingHandlers.initIntlAutocomplete = {
                update: function (element, valueAccessor, allBindings, viewModel, bindingContext) {
                    if (this.intlAutocompleteInstance !== null || !ko.unwrap(valueAccessor())) {
                        return; // Autocomplete instance already created or element not visible.
                    }

                    this.intlAutocompleteInstance = new AutocompleteAddress(element, {
                        autocompleteUrl: this.settings.base_url + 'rest/V1/flekto/postcode-international/autocomplete',
                        addressDetailsUrl: this.settings.base_url + 'rest/V1/flekto/postcode-international/getdetails',
                        context: this.countryCode,
                    });

                    element.addEventListener('autocomplete-select', function (e) {
                        if (e.detail.precision === 'Address') {
                            this.loading(true);

                            this.intlAutocompleteInstance.getDetails(e.detail.context, function (result) {
                                this.setInputAddress(result[0].address);
                                this.toggleFields(true);
                                this.loading(false);
                            }.bind(this));
                        }
                    }.bind(this));

                    document.addEventListener('autocomplete-xhrerror', function (e) {
                        console.error('Autocomplete XHR error', e);
                        this.toggleFields(true);
                        this.loading(false);
                    }.bind(this));

                    // Clear the previous values when searching for a new address.
                    element.addEventListener('autocomplete-search', this.resetInputAddress.bind(this));
                }.bind(this)
            };
        },

        setInputAddress: function (address) {
            const streetInputs = this.street().elems(),
                addition = address.buildingNumberAddition === null ? '' : ' ' + address.buildingNumberAddition;

            if (streetInputs.length > 2) {
                streetInputs[0].value(address.street);
                streetInputs[1].value(String(address.buildingNumber));
                streetInputs[2].value(addition.trim());
            }
            else if (streetInputs.length > 1) {
                streetInputs[0].value(address.street);
                streetInputs[1].value(address.buildingNumber + addition);
            }
            else {
                streetInputs[0].value(address.street + ' ' + address.buildingNumber + addition);
            }

            this.city().value(address.locality);
            this.postcode().value(address.postcode);
        },

        resetInputAddress: function () {
            this.street().elems.each(function (streetInput) { streetInput.reset(); });
            this.city().reset();
            this.postcode().reset();
        },

        toggleFields: function (state) {
            if (this.settings.show_hide_address_fields === 'disable') {
                this.street(function (component) {
                    component.elems.each(function (streetInput) { streetInput.disabled(!state); });
                });
                this.city(function (component) { component.disabled(!state) });
                this.postcode(function (component) { component.disabled(!state) });
            }
            else if (this.settings.show_hide_address_fields === 'hide') {
                const fields = ['street', 'city', 'postcode'];

                for (let i in fields) {
                    this[fields[i]](function (component) {
                        component.visible(state)
                    });
                }
            }
        },

    });
});
