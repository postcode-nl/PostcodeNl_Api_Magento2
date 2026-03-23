define([
    'ko',
    'Magento_Ui/js/lib/knockout/template/renderer',
    'PostcodeEu_AddressValidation/js/lib/postcode-eu-autocomplete-address',
    'mage/translate',
], function (ko, renderer, AutocompleteAddress, $t) {
    'use strict';

    const addressDetailsCache = new Map();

    ko.bindingHandlers.initIntlAutocomplete = {
        update: function (element, valueAccessor, allBindings, viewModel, bindingContext) {
            if (viewModel.intlAutocompleteInstance !== null || !ko.unwrap(valueAccessor())) {
                return; // Autocomplete instance already created or element not visible.
            }

            viewModel.intlAutocompleteInstance = new AutocompleteAddress(element, {
                autocompleteUrl: viewModel.settings.api_actions.autocomplete,
                addressDetailsUrl: viewModel.settings.api_actions.addressDetails,
                context: viewModel.countryCode || 'NL',
                showLogo: viewModel.showLogo ?? true,
            });

            viewModel.inputElement = element;

            function getAddressDetails(context, callback) {
                if (addressDetailsCache.has(context)) {
                    callback(addressDetailsCache.get(context));
                    return;
                }

                viewModel.intlAutocompleteInstance.getDetails(context, (result) => {
                    callback(result);
                    addressDetailsCache.set(context, result);
                });
            }

            function selectAddress(selectedItem) {
                viewModel.loading(true);

                getAddressDetails(selectedItem.context, (result) => {
                    const isValidAddress = viewModel.validateAddress(result[0]);

                    viewModel.loading(false);
                    viewModel.address(isValidAddress ? result[0] : null);
                    viewModel.toggleFields(isValidAddress);
                    isValidAddress && viewModel.validate();
                });
            }

            element.addEventListener('autocomplete-select', (e) => {
                if (e.detail.precision === 'Address') {
                    selectAddress(e.detail);
                }
            });

            element.addEventListener('autocomplete-error', (e) => {
                console.error('Autocomplete XHR error', e);
                viewModel.toggleFields(true);
                viewModel.loading(false);
                viewModel.error($t('An error has occurred while retrieving address data. Please contact us if the problem persists.'));
            });

            // Clear the previous values when searching for a new address.
            element.addEventListener('autocomplete-search', () => {
                viewModel.resetInputAddress();
                viewModel.address(null);
            });
        }
    };

    renderer.addAttribute('initIntlAutocomplete');

});

