define([
    'uiCollection',
    'uiRegistry',
    'jquery',
    'jquery/validate',
], function (Collection, Registry, $) {
    'use strict';

    return Collection.extend({

        defaults: {
            validatorInstance: $('.form-address-edit').validate(),
            fields: {
                street: document.querySelector('.field.street'),
                country: document.querySelector('.field.country'),
                region: document.querySelector('.field.region'),
                city: document.querySelector('.field.city'),
                postcode: document.querySelector('.field.zip'),
            },
            inputs: {
                street: document.querySelectorAll('.field.street .input-text'),
                country: document.getElementById('country'),
                region: document.getElementById('region'),
                city: document.getElementById('city'),
                postcode: document.getElementById('zip'),
            },
            listens: {
                '${$.name}.address_autofill_intl:address': 'validateInputs',
                '${$.name}.address_autofill_nl:address': 'validateInputs',
            },
            tracks: {
                isLoading: true,
                countryCode: true,
                isCountryChanged: true,
            },
            isLoading: true,
            countryCode: '${$.inputs.country.value}',
            isCountryChanged: false,
        },

        initialize: function (config) {
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
            const fieldset = this.fields.city.parentNode;

            fieldset.insertBefore(this.fields.country, this.fields.street);
            fieldset.insertBefore(this.fields.postcode, this.fields.region);
            fieldset.insertBefore(this.fields.city, this.fields.region);
        },

        moveToForm: function () {
            this.fields.city.parentNode.insertBefore(
                document.querySelector('.address-autofill-fieldset'),
                this.fields.street
            );
        },

        validateComponentsOnSubmit: function () {
            const originalSubmitHandler = this.validatorInstance.settings.submitHandler,
                formButton = this.validatorInstance.currentForm.querySelector('[data-action=save-address]');

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

                    originalSubmitHandler();
                });
            }.bind(this);
        },

        validateInputs: function (address) {
            if (address === null) {
                return;
            }

            // Trigger jQuery validator.
            for (const input of [...this.inputs.street, this.inputs.city, this.inputs.postcode, this.inputs.region]) {
                this.validatorInstance.element(input);
            }

            // Trigger custom postcode validation (see Magento_Customer/js/addressValidation).
            this.inputs.postcode.dispatchEvent(new KeyboardEvent('keyup'));
        },

    })
});
