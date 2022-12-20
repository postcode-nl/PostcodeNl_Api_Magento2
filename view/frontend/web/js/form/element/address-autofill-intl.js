define([
    'Magento_Ui/js/form/element/abstract',
    'mage/translate',
    'Flekto_Postcode/js/ko/bindings/init-intl-autocomplete',
], function (Abstract, $t) {
    'use strict';

    return Abstract.extend({
        defaults: {
            loading: false,
            address: null,
            intlAutocompleteInstance: null,
        },

        initialize: function (config) {
            this._super();

            if (this.settings.show_hide_address_fields !== 'show') {
                this.validation['validate-callback'] = {
                    message: $t('Please enter an address and select it.'),
                    isValid: this.isValid.bind(this),
                };
                this.required(true);
            }

            this.additionalClasses['loading'] = this.loading;
            this.address.subscribe(this.setInputAddress.bind(this));

            return this;
        },

        initObservable: function () {
            this._super();
            this.observe('address loading');
            return this;
        },

        onChangeCountry: function (countryCode) {
            this.reset();

            const isEnabled = this.isEnabledCountry(countryCode);

            if (isEnabled && this.settings.nl_input_behavior === 'zip_house' && countryCode === 'NL') {
                this.visible(false);
                return;
            }

            this.visible(isEnabled);
            this.toggleFields(!isEnabled, true);

            if (isEnabled && this.intlAutocompleteInstance !== null) {
                // Reset address fields on country change.
                this.resetInputAddress();
                this.intlAutocompleteInstance.reset();
                this.intlAutocompleteInstance.setCountry(countryCode);
            }
        },

        isEnabledCountry: function (countryCode) {
            return this.settings.enabled_countries.indexOf(countryCode) > -1;
        },

        isValid: function () {
            return this.visible() === false || this.address() !== null;
        },

        getAddressParts: function (result) {
            const buildingNumber = `${result.address.buildingNumber || ''}`,
                buildingNumberAddition = `${result.address.buildingNumberAddition || ''}`;

            return {
                street: result.address.street,
                building: `${buildingNumber} ${buildingNumberAddition}`.trim(),
                buildingNumber: buildingNumber,
                buildingNumberAddition: buildingNumberAddition,
                locality: result.address.locality,
                postcode: result.address.postcode,
            };
        },

    });
});
