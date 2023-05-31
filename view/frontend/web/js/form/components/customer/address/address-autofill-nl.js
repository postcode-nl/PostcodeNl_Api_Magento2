define([
    'Flekto_Postcode/js/form/components/address-autofill-nl',
], function (Collection) {
    'use strict';

    return Collection.extend({
        defaults: {
            imports: {
                fields: '${$.parentName}:fields',
                inputs: '${$.parentName}:inputs',
                countryCode: '${$.parentName}:countryCode',
                isCountryChanged: '${$.parentName}:isCountryChanged',
                onChangeCountry: '${$.parentName}:countryCode',
            },
        },

        setInputAddress: function (address) {
            if (address === null) {
                return;
            }

            const addressParts = this.getAddressParts(address);

            if (this.inputs.street.length > 2) {
                this.inputs.street[0].value = addressParts.street;
                this.inputs.street[1].value = addressParts.houseNumber;
                this.inputs.street[2].value = addressParts.houseNumberAddition;
            } else if (this.inputs.street.length > 1) {
                this.inputs.street[0].value = addressParts.street;
                this.inputs.street[1].value = addressParts.house;
            } else {
                this.inputs.street[0].value = addressParts.street + ' ' + addressParts.house;
            }

            this.inputs.city.value = addressParts.city;
            this.inputs.postcode.value = addressParts.postcode;
            this.inputs.region.value = addressParts.province;
        },

        resetInputAddress: function () {
            if (this.isCountryChanged) {
                [
                    ...this.inputs.street,
                    this.inputs.city,
                    this.inputs.postcode,
                    this.inputs.region,
                ].forEach(input => input.value = '');

                this.status(null);
            }
        },

        toggleFields: function (state, force) {
            switch (this.settings.show_hide_address_fields) {
                case 'disable':
                    [
                        ...this.inputs.street,
                        this.inputs.city,
                        this.inputs.postcode,
                        this.inputs.region,
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
                    for (const name of ['street', 'city', 'postcode', 'region']) {
                        this.fields[name].style.display = state ? '' : 'none';
                    }
                break;
            }
        },

    });
});
