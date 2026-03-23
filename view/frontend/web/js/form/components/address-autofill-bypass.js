define([
    'Magento_Ui/js/form/components/html',
], function (Html) {
    'use strict';

    return Html.extend({
        defaults: {
            modules: {
                autofillIntl: '${$.parentName}.address_autofill_intl',
            },
            listens: {
                '${ $.parentName }.address_autofill_intl:error': 'visible',
            },
            visible: false,
            template: 'PostcodeEu_AddressValidation/content/address-autofill-bypass',
            tooltipTpl: 'ui/form/element/helper/tooltip',
            additionalClasses: {
                'address-autofill-bypass': true,
            },
        },

        editAddress: function () {
            this.visible(false);
            this.autofillIntl().visible(false);
            this.autofillIntl().toggleFields(true, true);
        },

    });
});
