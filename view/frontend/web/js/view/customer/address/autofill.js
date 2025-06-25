define([
    'uiCollection',
    'uiRegistry',
    'jquery',
    'jquery/validate',
], function (Collection, Registry, $) {
    'use strict';

    return Collection.extend({

        defaults: {
            validatorInstance: $('#form-validate').validate(),
            fieldset: document.getElementById('zip').closest('fieldset'),
            fields: {
                street: document.querySelector('.field.street'),
                country: document.querySelector('.field.country'),
                region: document.querySelector('.field.region'),
                city: document.querySelector('[name=city]').closest('.field'), // Workaround missing city class.
                postcode: document.querySelector('.field.zip'),
            },
            inputs: {
                street: document.querySelectorAll('.field.street .input-text'),
                country: document.getElementById('country'),
                regionId: document.getElementById('region_id'),
                region: document.getElementById('region'),
                city: document.getElementById('city'),
                postcode: document.getElementById('zip'),
                toArray: function () {
                    return [...this.street, this.city, this.postcode, this.regionId, this.region];
                },
                getStreetValue: function () {
                    return [...this.street].map((input) => input.value).join(' ').trim();
                },
            },
            listens: {
                '${$.name}.address_autofill_intl:address': 'validateInputs',
                '${$.name}.address_autofill_nl:address': 'validateInputs',
            },
            tracks: {
                countryCode: true,
                isCountryChanged: true,
            },
            countryCode: '${$.inputs.country.value}',
            isCountryChanged: false,
        },

        initialize: function () {
            this._super();

            if (this.settings.change_fields_position) {
                this.changeFieldsPosition();
            }

            this.moveToForm();
            this.validateComponentsOnSubmit();
            this.inputs.country.addEventListener('change', function (e) {
                this.isCountryChanged = true;
                this.countryCode = e.target.value;
            }.bind(this));

            return this;
        },

        changeFieldsPosition: function () {
            this.fieldset.insertBefore(this.fields.country, this.fields.street);
            this.fieldset.insertBefore(this.fields.postcode, this.fields.region);
            this.fieldset.insertBefore(this.fields.city, this.fields.region);
        },

        moveToForm: function () {
            this.fieldset.insertBefore(
                document.querySelector('.address-autofill-fieldset'),
                this.fields.street
            );
        },

        validateComponentsOnSubmit: function () {
            const originalSubmitHandler = this.validatorInstance.settings.submitHandler;

            let isValidating = false;

            this.validatorInstance.settings.submitHandler = function () {
                if (isValidating) {
                    return;
                }

                isValidating = true;

                const childComponents = [`${this.name}.address_autofill_intl`],
                    nlComponent = this.getChild('address_autofill_nl');

                // NL component may be disabled. Add child names only if available.
                if (typeof nlComponent !== 'undefined') {
                    for (const child of nlComponent.elems()) {
                        childComponents.push(child.name);
                    }
                }

                Registry.get(childComponents, (...components) => {
                    isValidating = false;

                    if (components.some(component => component.validate()['valid'] === false)) {
                        return; // Invalid form, prevent submit.
                    }

                    originalSubmitHandler(this.validatorInstance.currentForm);
                });
            }.bind(this);
        },

        validateInputs: function (address) {
            if (address === null) {
                return;
            }

            // Trigger jQuery validator.
            for (const input of this.inputs.toArray()) {
                if (input.value === '') {
                    // Leave empty inputs to be validated later.
                    this.validatorInstance.resetElements([input]);
                } else {
                    this.validatorInstance.element(input);
                }
            }

            // Trigger custom postcode validation (see Magento_Customer/js/addressValidation).
            this.inputs.postcode.dispatchEvent(new KeyboardEvent('keyup'));
        },

    });
});
