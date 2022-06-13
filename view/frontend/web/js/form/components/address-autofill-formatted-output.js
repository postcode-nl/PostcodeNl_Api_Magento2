define([
    'Magento_Ui/js/form/components/html',
], function (Html) {
    'use strict';

    return Html.extend({
        defaults: {
            modules: {
                street: '${$.parentName}.street',
                city: '${$.parentName}.city',
                postcode: '${$.parentName}.postcode',
                regionIdInput: '${$.parentName}.region_id_input',
                houseNumberSelect: '${$.parentName}.address_autofill_nl.house_number_select',
            },
            imports: {
                renderNlAddress: '${$.parentName}.address_autofill_nl:address',
                nlAddressStatus: '${$.parentName}.address_autofill_nl:status',
                renderIntlAddress: '${$.parentName}.address_autofill_intl:address',
                onChangeCountry: '${$.parentName}.country_id:value',
            }
        },

        initialize: function () {
            this._super();
            this.visible(false);

            // Hide result if house number addition caption is selected.
            this.houseNumberSelect(function (component) {
                component.value.subscribe(function (value) {
                    if (typeof value === 'undefined') {
                        this.visible(false);
                    }
                }.bind(this));
            }.bind(this));

            return this;
        },

        onChangeCountry: function () {
            this.content('');
            this.visible(false);
        },

        renderNlAddress: function (address) {
            if (this.nlAddressStatus !== 'valid') {
                this.visible(false);
                return; // Waiting for house number addition to be selected.
            }

            const line1 = address.street + ' ' + address.houseNumber + (address.houseNumberAddition ? ' ' + address.houseNumberAddition : ''),
                line2 = address.postcode + ' ' + address.city;

            this.content(line1 + '<br>' + line2);
            this.visible(true);
        },

        renderIntlAddress: function (address) {
            if (address === null) {
                this.visible(false);
            }
            else {
                this.content(address.mailLines[0] +'<br>' + address.mailLines[1]);
                this.visible(true);
            }
        },
    });
});
