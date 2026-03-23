define([
    'PostcodeEu_AddressValidation/js/form/components/address-autofill-bypass',
], function (Html) {
    'use strict';

    return Html.extend({
        editAddress: function () {
            this._super();
            this.autofillIntl().street().elems()[0].focused(true); // Focus first street input.
        },

    });
});
