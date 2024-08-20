define([
    'Flekto_Postcode/js/form/element/address-autofill-intl',
    'uiRegistry',
    'mageUtils',
], function (AddressAutofillIntl, Registry, Utils) {
    'use strict';

    return AddressAutofillIntl.extend({
        defaults: {
            imports: {
                fields: '${$.parentName}:fields',
                inputs: '${$.parentName}:inputs',
                countryCode: '${$.parentName}:countryCode',
                isCountryChanged: '${$.parentName}:isCountryChanged',
                onChangeCountry: '${$.parentName}:countryCode',
            },

            searchInitialValue: true,
        },

        initialize: function () {
            this._super();

            this.visible(this.isEnabledCountry(this.countryCode));
            this.toggleFields(!this.visible());

            if (this.value() === '') {
                const postcode = this.inputs.postcode.value,
                    city = this.inputs.city.value,
                    streetAddress = [...this.inputs.street].map((input) => input.value).join(' '),
                    prefilledAddressValue = `${postcode} ${city} ${streetAddress}`.trim();

                if (prefilledAddressValue !== '') {
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

        setInputAddress: function (result) {
            const address = this.getAddressParts(result.address);

            if (this.inputs.street.length > 2) {
                this.inputs.street[0].value = address.street;
                this.inputs.street[1].value = address.buildingNumber;
                this.inputs.street[2].value = address.buildingNumberAddition;
            } else if (this.inputs.street.length > 1) {
                this.inputs.street[0].value = address.street;
                this.inputs.street[1].value = address.building;
            } else {
                this.inputs.street[0].value = address.street + ' ' + address.building;
            }

            this.inputs.city.value = address.locality;
            this.inputs.postcode.value = address.postcode;

            if (this.inputs.regionId.style.display !== 'none') {
                this.inputs.regionId.value = result.region.id ?? '';
            } else if (this.inputs.region.style.display !== 'none') {
                this.inputs.region.value = result.region.name ?? '';
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
