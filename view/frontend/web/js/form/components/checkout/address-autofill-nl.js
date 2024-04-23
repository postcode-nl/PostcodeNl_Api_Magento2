define([
    'Flekto_Postcode/js/form/components/address-autofill-nl',
    'uiRegistry',
    'Flekto_Postcode/js/model/address-nl',
], function (AddressAutofillNl, Registry, AddressNlModel) {
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

            this.addressFields = Registry.async([
                `${this.parentName}.street`,
                `${this.parentName}.city`,
                `${this.parentName}.postcode`,
                `${this.parentName}.region_id_input`,
            ]);

            this.countrySelect((component) => {
                this.visible(component.value() === 'NL');
                component.value.subscribe((value) => { this.onChangeCountry(value); });
            });

            if (this.address() !== null) {
                const { postcode, house } = this.getAddressParts(this.address());

                this.childPostcode((component) => { component.value(postcode); });
                this.childHouseNumber((component) => { component.value(house); });

                if (this.status() === AddressNlModel.status.ADDITION_INCORRECT) {
                    this.childHouseNumberSelect((component) => {
                        component.setOptions(this.address().houseNumberAdditions);
                    });
                }

                // Set values from stored address. Note that values may still get overwritten by checkout-data.
                this.addressFields(this.setInputAddress.bind(this, this.address()));
            }

            return this;
        },

        onChangeCountry: function (countryCode) {
            this.addressFields?.(this._super.bind(this, countryCode));
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
                this.status(AddressNlModel.status.PO_BOX_SHIPPING_NOT_ALLOWED);
                return false;
            }

            return this._super(address);
        },

    });
});
