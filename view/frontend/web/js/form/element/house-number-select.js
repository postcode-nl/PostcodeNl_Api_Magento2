define([
    'Magento_Ui/js/form/element/select',
    'mage/translate',
], function (Select, $t) {
    'use strict';

    return Select.extend({

        initialize: function () {
            this._super();

            if (window.checkoutConfig.flekto_postcode.settings.show_hide_address_fields !== 'show') {
                this.validation['validate-callback'] = {
                    message: $t('Please select a house number.'),
                    isValid: this.isValid.bind(this),
                };
                this.additionalClasses['required'] = true;
            }
        },

        isValid: function () {
            return this.visible() === false || typeof this.value() !== 'undefined';
        },

    });
});
