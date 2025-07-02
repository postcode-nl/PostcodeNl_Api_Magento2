define([
    'Magento_Ui/js/form/components/group',
    'uiRegistry',
    'ko',
], function (Group, Registry, ko) {
    'use strict';

    return Group.extend({
        initObservable: function () {
            this._super();

            this.disabled = ko.pureComputed({
                read: () => this.elems.first()?.disabled() ?? false,
                write: (val) => this.elems.map((elem) => elem.disabled(val)),
            });

            return this;
        },

        clearFields: function () {
            this.delegate('clear');
            return this;
        },

        clearErrors: function () {
            this.delegate('error', false);
            return this;
        },

        asyncSetValues: function (...args) {
            const lastChildIndex = this.initChildCount - 1,
                values = args.slice(0, lastChildIndex);

            // Join remaining args for last or single child.
            values.push(args.slice(lastChildIndex).join(' ').trim());

            values.forEach((v, i) => { Registry.async(`${this.name}.${i}`)('value', v); });
        },
    });
});
