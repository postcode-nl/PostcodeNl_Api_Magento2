define([
    'PostcodeEu_AddressValidation/js/form/element/address-autofill-intl',
    'PostcodeEu_AddressValidation/js/action/customer/address/get-validated-address',
    'uiRegistry',
    'mageUtils',
], function (AddressAutofillIntl, getValidatedAddress, Registry, Utils) {
    'use strict';

    return AddressAutofillIntl.extend({
        defaults: {
            imports: {
                fields: '${$.parentName}:fields',
                inputs: '${$.parentName}:inputs',
                countryCode: '${$.parentName}:countryCode',
                isCountryChanged: '${$.parentName}:isCountryChanged',
                onChangeCountry: '${$.parentName}:countryCode',
                settings: '${$.parentName}:settings',
            },
        },

        initialize: function () {
            this._super();

            this.visible(this.isEnabledCountry(this.countryCode));
            this.toggleFields(!this.visible(), true);

            if (this.visible() && this.value() === '') {
                const postcode = this.inputs.postcode.value,
                    city = this.inputs.city.value,
                    streetAndBuilding = this.inputs.getStreetValue(),
                    prefilledAddressValue = `${postcode} ${city} ${streetAndBuilding}`.trim();

                if (prefilledAddressValue === '') {
                    return;
                }

                this.resetInputAddress();

                if (streetAndBuilding && postcode && city) {
                    this.loading(true);

                    getValidatedAddress(this.countryCode, streetAndBuilding, postcode, city)
                        .then((result) => {
                            if (result !== null) {
                                this.address(result);
                            }
                        })
                        .finally(() => {
                            this.loading(false);
                            this.value(prefilledAddressValue);
                            this.inputElement.classList.remove('postcodenl-autocomplete-address-input-blank');
                        });
                } else {
                    // Set incomplete value to trigger validation and show error message.
                    this.value(prefilledAddressValue);
                }
            }

            return this;
        },

        onChangeCountry: function (countryCode) {
            if (this.isCountryChanged) {
                return this._super(countryCode);
            }
        },

        resetInputAddress: function () {
            this.inputs.toArray().forEach(input => { input.value = ''; });
        },

        toggleFields: function (state, force) {
            if (this.countryCode === 'NL' && Utils.isObject(Registry.get(`${this.parentName}.address_autofill_nl`))) {
                return; // Toggle will be handled by NL component.
            }

            switch (this.settings.show_hide_address_fields) {
            case 'disable':
                this.inputs.toArray().forEach(input => { input.disabled = !state; });
                break;
            case 'format':
                if (!force) {
                    if (this.fields.street.style.display === 'none') {
                        return;
                    }

                    state = false;
                }

            /* falls through */
            case 'hide':
                for (const name of ['street', 'city', 'postcode']) {
                    this.fields[name].style.display = state ? '' : 'none';
                }
                break;
            }
        },

    });
});
