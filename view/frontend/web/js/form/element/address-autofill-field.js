define([
    'Magento_Ui/js/form/element/abstract',
], function (Abstract) {
    'use strict';

    return Abstract.extend({

        defaults: {
            imports: {
                onSettings: '${$.parentName}:settings',
            },
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
