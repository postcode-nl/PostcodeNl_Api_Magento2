define([
    'Flekto_Postcode/js/form/element/address-autofill-intl',
    'mage/translate',
], function (AddressAutofillIntl, $t) {
    'use strict';

    return AddressAutofillIntl.extend({
        defaults: {
            imports: {
                fields: '${$.parentName}:fields',
                inputs: '${$.parentName}:inputs',
                countryCode: '${$.parentName}:countryCode',
                onChangeCountry: '${$.parentName}:countryCode',
            },
        },

        initialize: function () {
            this._super();

            if (this.countryCode === '') {
                this.visible(false);
            }

            if (this.settings.fixedCountry !== null) {
                this.countryCode = this.settings.fixedCountry;
                this.onChangeCountry(this.countryCode);
            }

            return this;
        },

        setInputAddress: function (result) {
            if (result === null) {
                return;
            }

            const address = this.getAddressParts(result);

            if (this.inputs.street.length > 2) {
                this.inputs.street[0].value = address.street;
                this.inputs.street[1].value = address.buildingNumber;
                this.inputs.street[2].value = address.buildingNumberAddition;
            }
            else if (this.inputs.street.length > 1) {
                this.inputs.street[0].value = address.street;
                this.inputs.street[1].value = address.building;
            }
            else {
                this.inputs.street[0].value = address.street + ' ' + address.building;
            }

            this.inputs.city.value = address.locality;
            this.inputs.postcode.value = address.postcode;
        },

        resetInputAddress: function () {
            [
                ...this.inputs.street,
                this.inputs.city,
                this.inputs.postcode,
            ].forEach(input => input.value = '');

            this.address(null);
        },

        toggleFields: function (state, force) {
            switch (this.settings.show_hide_address_fields) {
                case 'disable':
                    [
                        ...this.inputs.street,
                        this.inputs.city,
                        this.inputs.postcode,
                    ].forEach(input => input.disabled = !state);
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
