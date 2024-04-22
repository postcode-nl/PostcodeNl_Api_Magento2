define([
    'ko',
    'Magento_Ui/js/lib/knockout/template/renderer',
    'Flekto_Postcode/js/lib/postcode-eu-autocomplete-address',
    'mage/translate',
], function (ko, renderer, AutocompleteAddress, $t) {
    'use strict';

    ko.bindingHandlers.initIntlAutocomplete = {
        update: function (element, valueAccessor, allBindings, viewModel, bindingContext) {
            if (viewModel.intlAutocompleteInstance !== null || !ko.unwrap(valueAccessor())) {
                return; // Autocomplete instance already created or element not visible.
            }

            viewModel.intlAutocompleteInstance = new AutocompleteAddress(element, {
                autocompleteUrl: viewModel.settings.base_url + 'postcode-eu/V1/international/autocomplete',
                addressDetailsUrl: viewModel.settings.base_url + 'postcode-eu/V1/international/address',
                context: viewModel.countryCode || 'NL',
            });

            const selectAddress = function (selectedItem) {
                viewModel.loading(true);

                viewModel.intlAutocompleteInstance.getDetails(selectedItem.context, (result) => {
                    const isValidAddress = viewModel.validateAddress(result[0]);

                    viewModel.loading(false);
                    viewModel.address(isValidAddress ? result[0] : null);
                    viewModel.toggleFields(isValidAddress);
                    isValidAddress && viewModel.validate();
                });
            };

            // If initialized with a value that leads to exactly one address, select it.
            if (viewModel.searchInitialValue && viewModel.value() !== '') {
                element.addEventListener(
                    'autocomplete-response',
                    (response) => {
                        const matches = response.detail.matches;

                        if (matches.length === 1 && matches[0].precision === 'Address') {
                            selectAddress(matches[0]);
                        }
                    },
                    { once: true }
                );

                viewModel.intlAutocompleteInstance.search(element, { term: viewModel.value(), showMenu: false });
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
            element.addEventListener('autocomplete-search', viewModel.resetInputAddress.bind(viewModel));
        }
    };

    renderer.addAttribute('initIntlAutocomplete');

});
