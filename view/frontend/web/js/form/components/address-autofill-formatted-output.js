define([
    'Magento_Ui/js/form/components/html',
], function (Html) {
    'use strict';

    return Html.extend({

        defaults: {
            imports: {
                renderIntlAddress: '${$.parentName}.address_autofill_intl:address',
                renderNlAddress: '${$.parentName}.address_autofill_nl:address',
                onStatus: '${$.parentName}.address_autofill_nl:status',
                onChangeHouseNumberSelect: '${$.parentName}.address_autofill_nl.house_number_select:value',
            },
            modules: {
                addressAutofillIntl: '${$.parentName}.address_autofill_intl',
                addressAutofillNl: '${$.parentName}.address_autofill_nl',
            },
            template: 'Flekto_Postcode/content/address-autofill-formatted-output',
            visible: false,
            additionalClasses: {
                'address-autofill-formatted-output': true,
            },
        },

        onChangeCountry: function () {
            this.content('');
            this.visible(false);
            this.renderStoredNlAddress();
            this.renderStoredIntlAddress();
        },

        onChangeHouseNumberSelect: function (value) {
            // Hide result if house number addition caption is selected.
            if (typeof value === 'undefined') {
                this.visible(false);
            }
        },

        renderStoredNlAddress: function () {
            if (this.countryCode === 'NL' && this.addressAutofillNl()?.status() === 'valid') {
                this.renderNlAddress(this.addressAutofillNl().address());
            }
        },

        renderStoredIntlAddress: function () {
            if (
                this.addressAutofillIntl()?.visible()
                && this.addressAutofillIntl()?.address()?.country?.iso2Code === this.countryCode
            ) {
                this.renderIntlAddress(this.addressAutofillIntl().address());
            }
        },

        renderIntlAddress: function (address) {
            if (address === null) {
                this.visible(false);
                return;
            }

            this.content(address.mailLines.join('<br>'));
            this.visible(true);
        },

        renderNlAddress: function (address) {
            if (address === null) {
                this.visible(false);
                return;
            }

            this.content(
                `${address.street} ${address.houseNumber}${address.houseNumberAddition ? ' ' + address.houseNumberAddition : ''}
                <br>
                ${address.postcode} ${address.city}`
            );
            this.visible(true);
        },

        onStatus: function (status) {
            this.visible(status === 'valid');
        },

    });
});
