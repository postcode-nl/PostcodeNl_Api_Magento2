define([
    'Magento_Ui/js/form/element/abstract',
], function (Abstract) {
    'use strict';

    return Abstract.extend({

        defaults: {
            imports: {
                settings: '${$.parentName}:settings',
            },
        },

        initialize: function () {
            this._super();

            if(this.settings) {
                if (this.settings.show_hide_address_fields !== 'show') {
                    this.validation['required-entry'] = true;
                    this.required(true);
                }
            }

            return this;
        },
    });
});
