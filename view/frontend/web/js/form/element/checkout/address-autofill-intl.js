define([
    'Flekto_Postcode/js/form/element/address-autofill-intl',
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
                regionIdInput: '${$.parentName}.region_id_input',
            },
            imports: {
                countryCode: '${$.parentName}.country_id:value',
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
            const addressParts = this.getAddressParts(result.address),
                streetInputs = this.street().elems();

            if (streetInputs.length > 2) {
                streetInputs[0].value(addressParts.street);
                streetInputs[1].value(addressParts.buildingNumber);
                streetInputs[2].value(addressParts.buildingNumberAddition);
            } else if (streetInputs.length > 1) {
                streetInputs[0].value(addressParts.street);
                streetInputs[1].value(addressParts.building);
            } else {
                streetInputs[0].value(`${addressParts.street} ${addressParts.building}`);
            }

            this.city().value(addressParts.locality);
            this.postcode().value(addressParts.postcode);
        },

        resetInputAddress: function () {
            this.city().clear().error(false);
            this.postcode().clear().error(false);
            this.regionIdInput().clear().error(false);

            // Must run last because the checkout data in local storage will not change if the street fields are empty.
            this.street().elems.each((streetInput) => streetInput.clear().error(false));
        },

        toggleFields: function (state, force) {
            if (this.countryCode === 'NL' && Utils.isObject(Registry.get(`${this.parentName}.address_autofill_nl`))) {
                return; // Toggle will be handled by NL component.
            }

            switch (this.settings.show_hide_address_fields) {
            case 'disable':
                let j = 4;

                while (j--) {
                    Registry.async(`${this.street().name}.${j}`)('disabled', !state);
                }

                this.city((component) => component.disabled(!state));
                this.postcode((component) => component.disabled(!state));
                break;
            case 'format':
                if (!force) {
                    if (!this.street().visible()) {
                        return; // Assume fields are already hidden, nothing more to do.
                    }

                    state = false; // To hide fields.
                }

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
                && this.parentScope.split('.')[0] === 'shippingAddress'
            ) {
                this.error($t('Sorry, we cannot ship to a PO Box address.'));
                return false;
            }

            return this._super();
        },

    });
});
