define([
    'Magento_Ui/js/form/element/abstract',
], function (Abstract) {
    'use strict';

    return Abstract.extend({

        defaults: {
            validation: {
                'required-entry': window.checkoutConfig.flekto_postcode.settings.show_hide_address_fields !== 'show',
            },
        },
    });
});
