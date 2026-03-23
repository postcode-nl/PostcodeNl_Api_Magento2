define([
    'Magento_Ui/js/form/element/abstract',
], function (Abstract) {
    'use strict';

    return Abstract.extend({

        defaults: {
            imports: {
                onSettings: '${$.parentName}:settings',
                visible: '${ $.parentName }:visible',
            },
            template: 'ui/form/field',
            elementTmpl: 'PostcodeEu_AddressValidation/form/element/address-autofill-field',
            visible: false,
        },

        onSettings: function (settings) {
            if (settings.show_hide_address_fields === 'show') {
                return;
            }

            this.required(true);
            this.validation['required-entry'] = true;
        },

    });
});
