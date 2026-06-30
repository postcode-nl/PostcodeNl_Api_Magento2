define([
    'mage/translate',
], function ($t) {
    'use strict';

    return {
        isAvailable: true,

        get defaultUnavailableMessage() {
            return $t('Sorry, the address lookup service is temporarily unavailable.');
        },
    };
});
