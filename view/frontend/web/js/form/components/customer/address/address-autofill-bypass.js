define([
    'PostcodeEu_AddressValidation/js/form/components/address-autofill-bypass',
], function (Html) {
    'use strict';

    return Html.extend({
        editAddress: function () {
            this._super();
            this.autofillIntl().inputs.street[0].focus(); // Focus first street input.
        },

    });
});
