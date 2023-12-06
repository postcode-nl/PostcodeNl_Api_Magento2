define([
    'Flekto_Postcode/js/form/components/address-autofill-nl',
    'uiRegistry',
], function (AddressAutofillNl, Registry) {
    'use strict';

    return AddressAutofillNl.extend({
        defaults: {
            imports: {
                countryCode: '${$.parentName}.country_id:value',
                onChangeCountry: '${$.parentName}.country_id:value',
            },
            modules: {
                street: '${$.parentName}.street',
                city: '${$.parentName}.city',
                postcode: '${$.parentName}.postcode',
                regionIdInput: '${$.parentName}.region_id_input',
            },
            addressFields: null,
        },

        onChangeCountry: function () {
            if (this.addressFields === null) {
                this.addressFields = Registry.async([
                    `${this.parentName}.street`,
                    `${this.parentName}.city`,
                    `${this.parentName}.postcode`,
                    `${this.parentName}.region_id_input`,
                ]);
            }

            // Wait for address fields to be available.
            this.addressFields(this._super.bind(this));
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
            this.street().elems.each((streetInput) => streetInput.clear().error(false));
            this.city().clear().error(false);
            this.postcode().clear().error(false);
            this.regionIdInput().clear().error(false);
            this.status(null);
        },

        toggleFields: function (state, force) {
            if (!this.isNl()) {
                // Always re-enable region.
                // This is not needed for .visible() because the region field has its own logic for that.
                this.regionIdInput((component) => component.enable());
                return;
            }

            switch (this.settings.show_hide_address_fields) {
                case 'disable':
                    {
                        for (const field of ['city', 'postcode', 'regionIdInput']) {
                            this[field](component => component.disabled(!state));
                        }

                        let j = 4;

                        while (j--) {
                            Registry.async(`${this.street().name}.${j}`)('disabled', !state);
                        }
                    }
                break;
                case 'format':
                    if (!force) {
                        if (!this.street().visible()) {
                            return;
                        }

                        state = false;
                    }

                    /* falls through */
                case 'hide':
                    for (const field of ['street', 'city', 'postcode', 'regionIdInput']) {
                        this[field](component => component.visible(state));
                    }
                break;
            }
        },

    });
});
