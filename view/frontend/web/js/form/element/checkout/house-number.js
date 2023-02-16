define([
    'jquery',
    'Flekto_Postcode/js/form/element/house-number',
    'Magento_Checkout/js/checkout-data'
], function ($, houseNumberField, checkoutData) {
    'use strict';

    return houseNumberField.extend({

        initialize: function () {
            this._super();

            var shippingAddressData = checkoutData.getShippingAddressFromData();
            if ($.isEmptyObject(shippingAddressData) ||
                typeof shippingAddressData.street == 'undefined') {
                return this;
            }

            var houseNumberWithAddition = '';
            if (Object.keys(shippingAddressData.street).length >= 2) {
                houseNumberWithAddition = houseNumberWithAddition.concat(shippingAddressData.street[1])
            }
            if (Object.keys(shippingAddressData.street).length >= 3) {
                houseNumberWithAddition = houseNumberWithAddition.concat(' ', shippingAddressData.street[2])
            }

            this.value(houseNumberWithAddition);

            return this;
        },

    });
});
