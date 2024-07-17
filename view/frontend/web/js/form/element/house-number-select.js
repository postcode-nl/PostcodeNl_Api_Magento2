define([
    'Magento_Ui/js/form/element/select',
    'mage/translate',
], function (Select, $t) {
    'use strict';

    return Select.extend({

        defaults: {
            template: 'ui/form/field',
            visible: false,
            imports: {
                onSettings: '${ $.parentName }:settings',
                onParentVisible: '${ $.parentName }:visible',
            },
            listens: {
                options: 'onOptions',
            },
            additionalClasses: {
                'address-autofill-nl-house-number-select': true,
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

        onParentVisible: function (isParentVisible) {
            this.visible(isParentVisible && this.options().length > 0);
        },

        onOptions: function (options) {
            this.visible(options.length > 0);
        },

    });
});
