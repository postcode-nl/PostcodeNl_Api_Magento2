define([
    'Magento_Ui/js/form/element/abstract',
    'uiRegistry',
    'ko',
    'Flekto_Postcode/js/lib/postcode-eu-autocomplete-address',
], function (Abstract, Registry, ko, AutocompleteAddress) {
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
        address: ko.observable(),

        initialize: function () {
            this._super();

            this.bindKoHandler();
            this.additionalClasses['loading'] = this.loading;
            this.address.subscribe(this.setInputAddress.bind(this));

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

        bindKoHandler: function () {
            ko.bindingHandlers.initIntlAutocomplete = {
                update: function (element, valueAccessor, allBindings, viewModel, bindingContext) {
                    if (viewModel.intlAutocompleteInstance !== null || !ko.unwrap(valueAccessor())) {
                        return; // Autocomplete instance already created or element not visible.
                    }

                    viewModel.intlAutocompleteInstance = new AutocompleteAddress(element, {
                        autocompleteUrl: viewModel.settings.base_url + 'postcode-eu/V1/international/autocomplete',
                        addressDetailsUrl: viewModel.settings.base_url + 'postcode-eu/V1/international/address',
                        context: viewModel.countryCode,
                    });

                    element.addEventListener('autocomplete-select', function (e) {
                        if (e.detail.precision === 'Address') {
                            viewModel.loading(true);

                            viewModel.intlAutocompleteInstance.getDetails(e.detail.context, function (result) {
                                viewModel.address(result[0]);
                                viewModel.toggleFields(true);
                                viewModel.loading(false);
                            });
                        }
                    });

                    document.addEventListener('autocomplete-xhrerror', function (e) {
                        console.error('Autocomplete XHR error', e);
                        viewModel.toggleFields(true);
                        viewModel.loading(false);
                    });

                    // Clear the previous values when searching for a new address.
                    element.addEventListener('autocomplete-search', viewModel.resetInputAddress.bind(viewModel));
                }
            };
        },

        setInputAddress: function (result) {
            const address = result.address,
                streetInputs = this.street().elems(),
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

        toggleFields: function (state, force) {
            switch (this.settings.show_hide_address_fields)
            {
                case 'disable':
                    let j = 4;

                    while (j--)
                    {
                        Registry.get(this.street().name + '.' + j, function (element) {
                            element.disabled(!state);
                        });
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
                // Fallthrough
                case 'hide':
                    const fields = ['street', 'city', 'postcode'];

                    for (let i in fields) {
                        this[fields[i]](function (component) {
                            component.visible(state)
                        });
                    }
                break;
            }
        },

    });
});
