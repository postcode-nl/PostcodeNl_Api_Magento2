define([
    'PostcodeEu_AddressValidation/js/form/element/address-autofill-intl',
    'mage/translate',
], function (AddressAutofillIntl, $t) {
    'use strict';

    return AddressAutofillIntl.extend({
        defaults: {
            showLogo: false,
            imports: {
                settings: '${$.parentName}:settings',
                inputs: '${$.parentName}:inputs',
                countryCode: '${$.parentName}:countryCode',
                onChangeCountry: '${$.parentName}:countryCode',
            },
            modules: {
                parent: '${$.parentName}',
            },
        },

        initialize: function () {
            this._super();
            this.required(false);
            return this;
        },

        validateAddress: function (address) {
            if (
                this.settings.allow_pobox_shipping === false
                && address.isPoBox
                && this.parent().addressType === 'shipping'
            ) {
                this.parent().error($t('This address is a PO box'));
                return false;
            }

            return this._super(address);
        },

        resetInputAddress: function () {
            this.parent().error(false);
        },

        toggleFields: function () { /* Ignore */ },

    });
});
