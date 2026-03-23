define([
    'Magento_Ui/js/form/components/html',
    'PostcodeEu_AddressValidation/js/model/address-nl',
], function (Html, AddressNlModel) {
    'use strict';

    return Html.extend({

        defaults: {
            imports: {
                onChangeHouseNumberSelect: '${$.parentName}.address_autofill_nl.house_number_select:value',
            },
            modules: {
                addressAutofillIntl: '${$.parentName}.address_autofill_intl',
                addressAutofillNl: '${$.parentName}.address_autofill_nl',
            },
            template: 'PostcodeEu_AddressValidation/content/address-autofill-formatted-output',
            visible: false,
            additionalClasses: {
                'address-autofill-formatted-output': true,
            },
        },

        initialize: function () {
            this._super();

            this.addressAutofillNl((component) => {
                component.status.subscribe(this.onStatusNl.bind(this));
                component.address.subscribe((address) => {
                    if (component.status() === AddressNlModel.status.VALID) {
                        this.renderNlAddress(address);
                    }
                });
            });

            this.addressAutofillIntl((component) => {
                component.address.subscribe(this.renderIntlAddress.bind(this));
            });

            return this;
        },

        onChangeCountry: function () {
            this.content('');
            this.visible(false);
            this.renderStoredNlAddress();
            this.renderStoredIntlAddress();
        },

        onChangeHouseNumberSelect: function (value) {
            // Hide result if house number addition caption is selected.
            if (this.addressAutofillNl()?.childHouseNumberSelect().visible() && typeof value === 'undefined') {
                this.visible(false);
            }
        },

        renderStoredNlAddress: function () {
            if (this.countryCode === 'NL' && this.addressAutofillNl()?.status() === AddressNlModel.status.VALID) {
                this.renderNlAddress(this.addressAutofillNl().address());
            }
        },

        renderStoredIntlAddress: function () {
            if (
                this.addressAutofillIntl()?.isEnabledCountry(this.countryCode)
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

        onStatusNl: function (status) {
            if (status === AddressNlModel.status.VALID) {
                this.renderNlAddress(this.addressAutofillNl().address());
                this.visible(true);
            } else {
                this.visible(false);
            }
        },

    });
});
