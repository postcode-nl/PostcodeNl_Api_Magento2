define([
    'uiRegistry',
], function (Registry) {
    'use strict';

    function validateAddress(country, streetAndBuilding, postcode, locality) {
        const params = [
                'streetAndBuilding=' + encodeURIComponent(streetAndBuilding ?? ''),
                'postcode=' + encodeURIComponent(postcode ?? ''),
                'locality=' + encodeURIComponent(locality ?? ''),
            ].join('&'),
            url = `${Registry.get('address_autofill').settings.api_actions.validate}/${country}?${params}`;

        return fetch(url).then((response) => {
            if (response.ok)
            {
                return response.json();
            }

            throw new Error(response.statusText);
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
                )
                {
                    return top;
                }

                return null;
            })
            .catch((error) => {
                console.error(error);
                return null;
            });
    };
});
