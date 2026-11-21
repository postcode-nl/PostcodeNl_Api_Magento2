define([
    'Magento_Ui/js/form/element/abstract',
    'ko',
], function (Abstract, ko) {
    'use strict';

    return Abstract.extend({

        defaults: {
            modules: {
                autofillIntl: '${$.parentName}.address_autofill_intl',
            },
            imports: {
                isIntlVisible: '${$.parentName}.address_autofill_intl:visible',
            },
            listens: {
                value: 'onValue',
                '${$.parentName}.address_autofill_intl:selectAddress': 'onSelectAddress',
            },
            template: 'ui/form/field',
            elementTmpl: 'PostcodeEu_AddressValidation/form/element/address-autofill-field',
            maxLength: 64, // Street lines share a varchar(255) column in sales_order_address.
            settings: {},
            countryCode: null,
            isIntlVisible: false,
            tracks: {
                countryCode: true,
                isIntlVisible: true,
            },
        },

        initObservable: function () {
            this._super();

            this.visible = ko.pureComputed(() =>
                this.isIntlVisible
                && Boolean(this.settings.residence_line_countries?.includes(this.countryCode))
            );

            return this;
        },

        initialize: function () {
            this._super();

            this.autofillIntl((component) => {
                this.setValueFromAddress(component.address());
                component.address.subscribe(this.setValueFromAddress.bind(this));
            });

            return this;
        },

        onChangeCountry: function (countryCode) {
            this.countryCode = countryCode;
            this.setValueFromAddress(this.autofillIntl()?.address());
        },

        /**
         * Show the residence line stored on the address.
         *
         * Value is kept if there's no address, or the address has no residence line. Cleared for other countries.
         *
         * @param {Object|null} address
         */
        setValueFromAddress: function (address) {
            if (!address) {
                return;
            }

            if (!this.isCurrentAddress(address)) {
                this.clear();
            } else if (typeof address.residenceLine !== 'undefined') {
                this.value(address.residenceLine);
            }
        },

        /**
         * Add the current residence line to a newly selected address, before it's stored.
         *
         * @param {Object} address - Copy of selected address
         */
        onSelectAddress: function (address) {
            const residenceLine = String(this.value() ?? '').trim();

            if (this.visible() && residenceLine !== '') {
                address.residenceLine = residenceLine;
            }
        },

        /**
         * Store the residence line on the address, which in turn updates the street lines.
         *
         * @param {string} value
         */
        onValue: function (value) {
            const address = this.autofillIntl()?.address();

            if (
                !this.valueChangedByUser
                || !this.visible()
                || !this.isCurrentAddress(address)
                || (address.residenceLine ?? '') === value
            ) {
                return;
            }

            this.autofillIntl().address({...address, residenceLine: value});
        },

        isCurrentAddress: function (address) {
            return Boolean(address) && address.country?.iso2Code === this.countryCode;
        },
    });
});
