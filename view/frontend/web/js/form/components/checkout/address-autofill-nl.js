define([
    'Flekto_Postcode/js/form/components/address-autofill-nl',
    'uiRegistry',
], function (AddressAutofillNl, Registry) {
    'use strict';

    return AddressAutofillNl.extend({
        defaults: {
            modules: {
                street: '${$.parentName}.street',
                city: '${$.parentName}.city',
                postcode: '${$.parentName}.postcode',
                regionIdInput: '${$.parentName}.region_id_input',
                countrySelect: '${$.parentName}.country_id',
            },
            statefull: {
                address: true,
                status: true,
            },
            addressFields: null,
        },

        initialize: function () {
            this._super();

            this.countrySelect((component) => {
                this.visible(component.value() === 'NL');
                component.value.subscribe((value) => { this.onChangeCountry(value); });
            });

            if (this.address() !== null && this.status() === 'houseNumberAdditionIncorrect') {
                this.childHouseNumberSelect((component) => {
                    component.setOptions(this.address().houseNumberAdditions);
                });
            }

            return this;
        },

        onChangeCountry: function (countryCode) {
            if (this.addressFields === null) {
                this.addressFields = Registry.async([
                    `${this.parentName}.street`,
                    `${this.parentName}.city`,
                    `${this.parentName}.postcode`,
                    `${this.parentName}.region_id_input`,
                ]);
            }

            // Wait for address fields to be available.
            this.addressFields(this._super.bind(this, countryCode));
        },

        setInputAddress: function (address) {
            const streetInputs = this.street().elems(),
                addressParts = this.getAddressParts(address);

            if (streetInputs.length > 2) {
                streetInputs[0].value(addressParts.street);
                streetInputs[1].value(addressParts.houseNumber);
                streetInputs[2].value(addressParts.houseNumberAddition);
            } else if (streetInputs.length > 1) {
                streetInputs[0].value(addressParts.street);
                streetInputs[1].value(addressParts.house);
            } else {
                streetInputs[0].value(`${addressParts.street} ${addressParts.house}`);
            }

            this.city().value(addressParts.city);
            this.postcode().value(addressParts.postcode);
            this.regionIdInput().value(addressParts.province);
        },

        resetInputAddress: function () {
            this.city().clear().error(false);
            this.postcode().clear().error(false);
            this.regionIdInput().clear().error(false);
            this.street().elems.each((streetInput) => streetInput.clear().error(false));
        },

        toggleFields: function (state) {
            if (this.countrySelect()?.value() !== 'NL') {
                // Always re-enable region.
                // This is not needed for .visible() because the region field has its own logic for that.
                this.regionIdInput((component) => component.enable());

                return; // Toggle will be handled by international component.
            }

            switch (this.settings.show_hide_address_fields) {
            case 'disable':
                for (const field of ['city', 'postcode', 'regionIdInput']) {
                    this[field](component => component.disabled(!state)); // eslint-disable-line no-loop-func
                }

                for (let j = 0; j < 4; j++) {
                    Registry.async(`${this.street().name}.${j}`)('disabled', !state);
                }

                break;
            case 'format':
                if (!this.street().visible()) {
                    return;
                }

                state = false;

            /* falls through */
            case 'hide':
                for (const field of ['street', 'city', 'postcode', 'regionIdInput']) {
                    this[field](component => component.visible(state));
                }
                break;
            }
        },

        validateAddress: function (address) {
            const houseNumber = this.childHouseNumber();

            if (
                this.settings.allow_pobox_shipping === false
                && address.addressType === 'PO box'
                && houseNumber.parentScope.split('.')[0] === 'shippingAddress'
            ) {
                this.status('poBoxShippingNotAllowed');
                return false;
            }

            return this._super(address);
        },

    });
});
