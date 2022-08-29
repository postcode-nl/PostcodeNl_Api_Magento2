define([
    'Magento_Ui/js/form/components/html',
], function (Html) {
    'use strict';

    return Html.extend({
        defaults: {
            modules: {
                autofillIntl: '${$.parentName}.address_autofill_intl',
                street: '${$.parentName}.street',
                city: '${$.parentName}.city',
                postcode: '${$.parentName}.postcode',
            },
            listens: {
                '${ $.parentName }.address_autofill_intl:error': 'visible',
            },
            visible: false,
        },

        editAddress: function () {
            this.visible(false);
            this.autofillIntl().visible(false);
            this.street().visible(true);
            this.street().elems().forEach(function (element) { element.disabled(false); });
            this.city().visible(true).disabled(false);
            this.postcode().visible(true).disabled(false);
            this.street().elems()[0].focused(true); // Focus first street input.
        },

    });
});
