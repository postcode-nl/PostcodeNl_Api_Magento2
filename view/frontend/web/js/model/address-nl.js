define([], function () {
    'use strict';

    return {
        lookupDelay: 750,
        postcodeRegex: /[1-9][0-9]{3}\s*[a-z]{2}/i,
        houseNumberRegex: /[1-9]\d{0,4}(?:\D.*)?$/i,
    };
});
