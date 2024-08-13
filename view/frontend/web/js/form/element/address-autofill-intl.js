define([
    'Magento_Ui/js/form/element/abstract',
    'mage/translate',
    'Flekto_Postcode/js/ko/bindings/init-intl-autocomplete',
], function (Abstract, $t) {
    'use strict';

    return Abstract.extend({
        defaults: {
            loading: false,
            address: null,
            intlAutocompleteInstance: null,
            settings: {},
            template: 'ui/form/field',
            elementTmpl: 'Flekto_Postcode/form/element/address-autofill-intl',
            visible: false,
            additionalClasses: {
                'address-autofill-intl-input': true,
            },
        },

        initialize: function () {
            this._super();

            if (this.settings.show_hide_address_fields !== 'show') {
                this.validation['validate-callback'] = {
                    message: $t('Please enter an address and select it.'),
                    isValid: this.isValid.bind(this),
                };
                this.required(true);
            }

            this.additionalClasses['loading'] = this.loading;

            this.address.subscribe((result) => {
                if (result === null) {
                    this.toggleFields(false);
                }
                else if (result.error) {
                    // See validateAddress() for error handling.
                    this.address(null); // Prevent storing the error in statefull address.
                }
                else {
                    this.setInputAddress(result);
                    this.toggleFields(true);
                }
            });

            return this;
        },

        initObservable: function () {
            this._super();
            this.observe('address loading');
            return this;
        },

        onChangeCountry: function (countryCode) {
            const isEnabled = this.isEnabledCountry(countryCode);

            this.reset();
            this.visible(isEnabled);

            if (!isEnabled) {
                this.toggleFields(true, true);
                return;
            }

            this.intlAutocompleteInstance?.reset();
            this.intlAutocompleteInstance?.setCountry(countryCode);

            if (this.address()?.country?.iso2Code === countryCode) {
                this.setInputAddress(this.address());
                this.toggleFields(true);
            } else {
                this.resetInputAddress();
                this.toggleFields(false);
            }
        },

        isEnabledCountry: function (countryCode) {
            return (
                this.settings.enabled_countries.includes(countryCode)
                && !(countryCode === 'NL' && this.settings.nl_input_behavior === 'zip_house')
            );
        },

        isValid: function () {
            return this.visible() === false || this.address() !== null;
        },

        validateAddress: function (address) {
            if (address.error) {
                this.error($t(address.message));
                return false;
            }

            return true;
        },

        getAddressParts: function (address) {
            const buildingNumber = `${address.buildingNumber || ''}`,
                buildingNumberAddition = `${address.buildingNumberAddition || ''}`;

            return {
                street: address.street,
                building: `${buildingNumber} ${buildingNumberAddition}`.trim(),
                buildingNumber: buildingNumber,
                buildingNumberAddition: buildingNumberAddition,
                locality: address.locality,
                postcode: address.postcode,
            };
        },

    });
});
