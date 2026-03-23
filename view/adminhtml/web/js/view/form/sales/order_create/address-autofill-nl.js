define([
    'PostcodeEu_AddressValidation/js/form/components/address-autofill-nl',
    'PostcodeEu_AddressValidation/js/model/address-nl',
    'mage/translate',
], function (Collection, AddressNlModel, $t) {
    'use strict';

    return Collection.extend({
        defaults: {
            error: '',
            listens: {
                status: 'onStatus',
                visible: 'toggleHouseNumberSelect',
                '${$.name}.house_number_select:options': 'toggleHouseNumberSelect',
            },
            imports: {
                addressType: '${$.parentName}:addressType',
                countryCode: '${$.parentName}:countryCode',
                inputs: '${$.parentName}:inputs',
                isCountryChanged: '${$.parentName}:isCountryChanged',
                onChangeCountry: '${$.parentName}:countryCode',
                settings: '${$.parentName}:settings',
            },
            exports: {
                error: '${$.parentName}:error',
            },
        },

        initObservable: function () {
            this._super();
            this.observe('error');
            return this;
        },

        onStatus: function (status) {
            if (status === AddressNlModel.status.NOT_FOUND) {
                this.error($t('Address not found'));
            }
        },

        validateAddress: function (address) {
            if (
                this.settings.allow_pobox_shipping === false
                && this.status() === AddressNlModel.status.VALID
                && this.addressType === 'shipping'
                && address.addressType === 'PO box'
            ) {
                this.error($t('This address is a PO box'));
                return false;
            }

            return this._super(address);
        },

        toggleHouseNumberSelect: function () {
            this.childHouseNumberSelect((component) => {
                component.visible(this.visible() && component.options().length > 0);
            });
        },

        resetInputAddress: function () {
            this.error(false);
        },

        toggleFields: function () { /* Ignore */ },

    });

});
