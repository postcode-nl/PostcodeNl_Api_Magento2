define([
    'Magento_Ui/js/form/element/select',
    'mage/translate',
], function (Select, $t) {
    'use strict';

    return Select.extend({

        defaults: {
            imports: {
                onSettings: '${$.parentName}:settings',
            },
        },

        isValid: function () {
            return this.visible() === false || typeof this.value() !== 'undefined';
        },

        onSettings: function (settings) {
            if (settings.show_hide_address_fields === 'show') {
                return;
            }

            this.required(true);
            this.validation['validate-callback'] = {
                message: $t('Please select a house number.'),
                isValid: this.isValid.bind(this),
            };
        },

    });
});
