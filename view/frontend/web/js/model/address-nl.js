define([], function () {
    'use strict';

    return Object.defineProperties({}, {
        lookupDelay: { value: 750 },
        postcodeRegex: { value: /[1-9][0-9]{3}\s*[a-z]{2}/i },
        houseNumberRegex: { value: /[1-9]\d{0,4}(?:\D.*)?$/i },
        status: {
            value: Object.defineProperties({}, {
                VALID: { value: 'valid' },
                NOT_FOUND: { value: 'notFound' },
                ADDITION_INCORRECT: { value: 'houseNumberAdditionIncorrect' },
                PO_BOX_SHIPPING_NOT_ALLOWED: { value: 'poBoxShippingNotAllowed' },
            }),
        },
    });
});
