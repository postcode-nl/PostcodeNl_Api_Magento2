define([
    'Magento_Ui/js/form/components/html',
], function (Html) {
    'use strict';

    return Html.extend({

        defaults: {
            imports: {
                renderIntlAddress: '${$.parentName}.address_autofill_intl:address',
                renderNlAddress: '${$.parentName}.address_autofill_nl:address',
                nlAddressStatus: '${$.parentName}.address_autofill_nl:status',
                onChangeHouseNumberSelect: '${$.parentName}.address_autofill_nl.house_number_select:value',
            },
        },

        initialize: function () {
            this._super();
            this.visible(false);

            return this;
        },

        onChangeCountry: function () {
            this.content('');
            this.visible(false);
        },

        onChangeHouseNumberSelect: function (value) {
            // Hide result if house number addition caption is selected.
            if (typeof value === 'undefined') {
                this.visible(false);
            }
        },

        renderIntlAddress: function (address) {
            if (address === null) {
                this.visible(false);
            } else {
                this.content(address.mailLines.join('<br>'));
                this.visible(true);
            }
        },

        renderNlAddress: function (address) {
            if (this.nlAddressStatus !== 'valid') {
                this.visible(false);
                return; // Waiting for house number addition to be selected.
            }

            this.content(
                `${address.street} ${address.houseNumber}${address.houseNumberAddition ? ' ' + address.houseNumberAddition : ''}
                <br>
                ${address.postcode} ${address.city}`
            );
                
            this.visible(true);
        },

    });
});
