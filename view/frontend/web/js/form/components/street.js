define([
    'Magento_Ui/js/form/components/group',
    'uiRegistry',
], function (Group, Registry) {
    'use strict';

    return Group.extend({
        defaults: {
            splitValues: false,
            imports: {
                settings: '${ $.provider }:postcodeEuConfig',
            },
        },

        clearFields: function () {
            this.delegate('clear');
            return this;
        },

        clearErrors: function () {
            this.delegate('error', false);
            return this;
        },

        asyncDelegate: function (method, ...args) {
            for (let i = 0; i < this.initChildCount; i++) {
                Registry.async(`${this.name}.${i}`)(method, ...args);
            }
        },

        asyncSetValues: function (...args) {
            const lastChildIndex = this.settings.split_street_values ? this.initChildCount - 1 : 0,
               values = args.slice(0, lastChildIndex);

            // Join remaining args for last or single child.
            values.push(args.slice(lastChildIndex).join(' ').trim());

            values.forEach((v, i) => { Registry.async(`${this.name}.${i}`)('value', v); });
        },
    });
});