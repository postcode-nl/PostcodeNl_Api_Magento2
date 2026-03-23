define([
    'PostcodeEu_AddressValidation/js/form/element/address-autofill-intl',
    'uiRegistry',
    'mage/translate',
    'mageUtils',
], function (AddressAutofillIntl, Registry, $t, Utils) {
    'use strict';

    return AddressAutofillIntl.extend({
        defaults: {
            modules: {
                street: '${$.parentName}.street',
                city: '${$.parentName}.city',
                postcode: '${$.parentName}.postcode',
                countrySelect: '${$.parentName}.country_id',
                regionId: '${$.parentName}.region_id',
                regionIdInput: '${$.parentName}.region_id_input',
            },
            imports: {
                countryCode: '${$.parentName}.country_id:value',
                settings: '${ $.provider }:postcodeEuConfig',
            },
            statefull: {
                address: true,
            },
        },

        initialize: function () {
            this._super();

            this.countrySelect((component) => {
                this.visible(this.isEnabledCountry(component.value()));
                this.toggleFields(!this.visible() || this.address()?.country?.iso2Code === component.value());
                component.value.subscribe((value) => { this.onChangeCountry(value); });
            });

            return this;
        },

        setInputAddress: function (result) {
            let streetLines = result.streetLines;

            // Result could be an old address from localStorage, without streetLines.
            if (typeof streetLines === 'undefined') {
                streetLines = [
                    result.address.street,
                    result.address.buildingNumber,
                    result.address.buildingNumberAddition,
                ];

                if (!this.settings.split_street_values) {
                    streetLines = [streetLines.join(' ')];
                }
            }

            // Street children may not yet be available at this point, so value needs to be set asynchronously.
            this.street().asyncSetValues(...streetLines);

            this.city().value(result.address.locality);
            this.postcode().value(result.address.postcode);

            if (this.regionId() && this.regionId().visible()) {
                if (result.region?.id) {
                    this.regionId().value(result.region.id);
                } else {
                    this.regionId().reset();
                }
            } else if (this.regionIdInput()) {
                if (result.region?.name) {
                    this.regionIdInput().value(result.region.name);
                } else {
                    this.regionIdInput().reset();
                }
            }
        },

        resetInputAddress: function () {
            this.city().clear().error(false);
            this.postcode().clear().error(false);
            this.regionId()?.clear().error(false);
            this.regionIdInput()?.clear().error(false);

            // Must run last because the checkout data in local storage will not change if the street fields are empty.
            this.street().clearFields().clearErrors();
        },

        toggleFields: function (state, force) {
            if (this.countryCode === 'NL' && Utils.isObject(Registry.get(`${this.parentName}.address_autofill_nl`))) {
                return; // Toggle will be handled by NL component.
            }

            switch (this.settings.show_hide_address_fields) {
            case 'disable':
                for (const field of ['street', 'city', 'postcode', 'regionId', 'regionIdInput']) {
                    this[field](component => component.disabled(!state)); // eslint-disable-line no-loop-func
                }
                break;
            case 'format':
                state = force && state; // Always hide fields unless forced otherwise.

            /* falls through */
            case 'hide':
                for (const field of ['street', 'city', 'postcode']) {
                    this[field](component => component.visible(state));
                }
                break;
            }
        },

        validateAddress: function (address) {
            if (
                this.settings.allow_pobox_shipping === false
                && address.isPoBox
                && this.parentScope === 'shippingAddress'
            ) {
                this.error($t('Sorry, we cannot ship to a PO Box address.'));
                return false;
            }

            return this._super(address);
        },

    });
});
