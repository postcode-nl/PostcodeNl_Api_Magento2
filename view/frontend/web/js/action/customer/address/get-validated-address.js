define([
    'uiRegistry',
], function (Registry) {
    'use strict';

    function validateAddress(country, streetAndBuilding = '', postcode = '', locality = '') {
        const settings = Registry.get('address_autofill').settings,
            url = new URL(settings.api_actions.validate.replace('{country}', country)),
            headers = {'X-Requested-With': 'XMLHttpRequest'};

        url.searchParams.set('streetAndBuilding', streetAndBuilding);
        url.searchParams.set('postcode', postcode);
        url.searchParams.set('locality', locality);

        return fetch(url.toString().replaceAll('+', '%20'), {headers}).then((response) => {
            if (response.ok) {
                return response.json();
            }

            throw new Error(response.statusText, {cause: response});
        });
    }

    return function getValidatedAddress(country, streetAndBuilding, postcode, locality) {
        return validateAddress(country, streetAndBuilding, postcode, locality)
            .then(([response]) => {
                const top = response.matches[0];

                if (
                    top?.status
                    && !top.status.isAmbiguous
                    && top.status.grade < 'C'
                    && ['Building', 'BuildingPartial'].includes(top.status.validationLevel)
                ) {
                    return top;
                }

                return null;
            })
            .catch((error) => {
                console.error(error);
                throw error;
            });
    };
});
