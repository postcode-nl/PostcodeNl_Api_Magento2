define([
    'jquery',
    'Flekto_Postcode/js/form/element/postcode',
    'Magento_Checkout/js/checkout-data'
], function ($, postcodeField, checkoutData) {
    'use strict';

    return postcodeField.extend({

        initialize: function () {
            this._super();

            var shippingAddressData = checkoutData.getShippingAddressFromData();
            if ($.isEmptyObject(shippingAddressData) ||
                typeof shippingAddressData.postcode == 'undefined') {
                return this;
            }

            this.value(shippingAddressData.postcode);

            return this;
        },

    });
});
