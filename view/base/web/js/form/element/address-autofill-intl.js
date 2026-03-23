define([
    'Magento_Ui/js/form/element/abstract',
    'mage/translate',
    'uiRegistry',
    'PostcodeEu_AddressValidation/js/ko/bindings/init-intl-autocomplete',
], function (Abstract, $t, Registry) {
    'use strict';

    return Abstract.extend({
        defaults: {
            loading: false,
            address: null,
            intlAutocompleteInstance: null,
            settings: {},
            template: 'ui/form/field',
            elementTmpl: 'PostcodeEu_AddressValidation/form/element/address-autofill-intl',
            visible: false,
            additionalClasses: {
                'address-autofill-intl-input': true,
            },
            inputs: null,
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
                && !(countryCode === 'NL' && Registry.has(this.parentName + '.address_autofill_nl'))
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

        setInputAddress: function (result) {
            if (this.inputs === null) {
                return;
            }

            const setValue = (input, value) => {
                input.value = value;
                input.dispatchEvent(new Event('change', {bubbles: true}));
            };

            for (let i = 0; i < result.streetLines.length; i++) {
                setValue(this.inputs.street[i], result.streetLines[i]);
            }

            setValue(this.inputs.city, result.address.locality);
            setValue(this.inputs.postcode, result.address.postcode);

            if (this.inputs.regionId && this.inputs.regionId.style.display !== 'none') {
                setValue(this.inputs.regionId, result.region.id ?? '');
            } else if (this.inputs.region && this.inputs.region.style.display !== 'none') {
                setValue(this.inputs.region, result.region.name ?? '');
            }
        },

    });
});
