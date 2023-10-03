define([
    'Flekto_Postcode/js/form/element/address-autofill-intl',
    'uiRegistry',
], function (AddressAutofillIntl, Registry) {
    'use strict';

    return AddressAutofillIntl.extend({
        defaults: {
            imports: {
                onChangeCountry: '${$.parentName}.country_id:value',
                countryCode: '${$.parentName}.country_id:value',
            },
            modules: {
                street: '${$.parentName}.street',
                city: '${$.parentName}.city',
                postcode: '${$.parentName}.postcode',
            },
        },

        initialize: function () {
            this._super();

            if (typeof this.countryCode === 'undefined') {
                this.visible(false);
            }

            if (this.settings.fixedCountry !== null) {
                this.countryCode = this.settings.fixedCountry;

                const fields = [
                    this.parentName + '.street',
                    this.parentName + '.city',
                    this.parentName + '.postcode',
                ];

                // Run country change handler when fields are available.
                Registry.async(fields)(this.onChangeCountry.bind(this, this.countryCode));
            }

            return this;
        },

        setInputAddress: function (result) {
            if (result === null) {
                return;
            }

            const addressParts = this.getAddressParts(result),
                streetInputs = this.street().elems();

            if (streetInputs.length > 2) {
                streetInputs[0].value(addressParts.street);
                streetInputs[1].value(addressParts.buildingNumber);
                streetInputs[2].value(addressParts.buildingNumberAddition);
            } else if (streetInputs.length > 1) {
                streetInputs[0].value(addressParts.street);
                streetInputs[1].value(addressParts.building)
            } else {
                streetInputs[0].value(`${addressParts.street} ${addressParts.building}`);
            }

            this.city().value(addressParts.locality);
            this.postcode().value(addressParts.postcode);
        },

        resetInputAddress: function () {
            this.street().elems.each((streetInput) => streetInput.clear().error(false));
            this.city().clear().error(false);
            this.postcode().clear().error(false);
            this.address(null);
        },

        toggleFields: function (state, force) {
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
                            return;
                        }

                        state = false;
                    }
                    /* falls through */
                case 'hide':
                    for (const field of ['street', 'city', 'postcode']) {
                        this[field](component => component.visible(state));
                    }
                break;
            }
        },

    });
});
