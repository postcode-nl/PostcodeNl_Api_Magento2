define([
    'Magento_Ui/js/form/components/group',
    'jquery',
    'Flekto_Postcode/js/postcode_autofill_client',
    'uiRegistry',
    'domReady!'
], function (Abstract, $, postcodenl, registry) {
    'use strict';

    const settings = window.checkoutConfig.flekto_postcode.settings,
        translations = settings.translations,
        intlAutocompleteCountries = JSON.parse(settings.supported_countries),
        selectors = {
            street: '[name^="street["]',
            postcode: '[name="postcode"]',
            city: '[name="city"]',
            province: '[name="region"]',
            country: '[name="country_id"]',
            nlPostcodeField: 'div[name$=".postcode"]',
            countryField: 'div[name$=".country_id"]',
            intlAutcomplete: '.postcodenl-autocomplete-address-input',
        },
        elements = {
            street: null,
            houseNumber: null,
            houseNumberAddition: null,
            postcode: null,
            city: null,
            province: null,
            country: null,
        },
        eventHandlers = {},
        nlPostcodeLookupDelay = 750;

    let intlAutocompleteInstance = null,
        nlPostcodeLookupValue = null,
        fieldsScope;

    return Abstract.extend({

        initialize: function () {

            this._super();

            const that = this;

            if (settings.enabled) {
                registry.async(this.provider)(function () {
                    that.initPostcodeConfig();
                    that.initModules();
                    that.initWatcher();
                });
            }

            return this;
        },

        initPostcodeConfig: function () {

            if (window.postcodenlConfig !== null && typeof window.postcodenlConfig === 'object') {

                this.logDebug('window.postcodenlConfig found, applying override');

                const that = this;

                $.each(window.postcodenlConfig, function (key, value) {
                    that.logDebug('Config override: ', key, value);
                    that[key] = value;
                });
            }
        },

        initWatcher: function () {

            const that = this;

            $(document).on('change', selectors.country, function () {

                const countryElement = this;

                // make sure field is set.
                setTimeout(function () {

                    that.logDebug(countryElement);
                    that.logDebug('country changed');
                    that.logDebug('nl_input_behavior: ', settings.nl_input_behavior);
                    that.logDebug('selected country: ', $("[name='country_id']:visible :selected ").text());
                    that.logDebug(countryElement.value);

                    that.updateFocusForm(countryElement);

                    if (settings.nl_input_behavior === 'zip_house' && countryElement.value === 'NL') {

                        that.logDebug('zip_house init');
                        that.hideIntlAutocomplete();
                        that.initNlPostcodeWatcher();
                        that.toggleFields(false);

                    } else if (intlAutocompleteCountries.indexOf(countryElement.value) > -1) {

                        that.logDebug('free input init');
                        that.hideNlPostcodeWatcher();
                        that.initIntlAutocomplete();
                        that.toggleFields(false);

                    } else {
                        that.hideIntlAutocomplete();
                        that.hideNlPostcodeWatcher();
                    }

                }, 200);

            });

            document.addEventListener('autocomplete-xhrerror', function (e) {
                that.logDebug('XHR Error!', e);
                that.toggleFields(true);
            });

        },

        updateFocusForm: function (el) {

            const form = el.form;

            this.logDebug('updateFocusForm', form);

            if (form) {
                const streetParts = form.querySelectorAll(selectors.street);

                fieldsScope = form;
                elements.street = streetParts[0];
                elements.houseNumber = streetParts[1];
                elements.houseNumberAddition = streetParts[2];
                elements.postcode = form.querySelector(selectors.postcode);
                elements.city = form.querySelector(selectors.city);
                elements.province = form.querySelector(selectors.province);
                elements.country = form.querySelector(selectors.country);
            }
        },

        initNlPostcodeWatcher: function() {

            this.logDebug('initNlPostcodeWatcher');

            const flektoNlZipPrefix = 'flekto_nl_zip';

            if (fieldsScope.querySelector('.' + flektoNlZipPrefix) !== null) {
                $(fieldsScope).find('.' + flektoNlZipPrefix).show();
                return;
            }

            const that = this,
                currentTimestamp = Date.now(),
                $nlPostcodeFieldClone = $(fieldsScope).find(selectors.nlPostcodeField).clone();

            $nlPostcodeFieldClone
                .removeAttr('data-bind')
                .prop('id', flektoNlZipPrefix + '_' + currentTimestamp)
                .prop('name', flektoNlZipPrefix + '_' + currentTimestamp)
                .removeClass('_error')
                .addClass(flektoNlZipPrefix)
                .insertAfter(fieldsScope.querySelector(selectors.countryField));

            $nlPostcodeFieldClone.find('.warning, .field-error').remove();
            $nlPostcodeFieldClone.find('span').text(translations.flekto_nl_zip_label);

            const $nlPostcodeInput = $nlPostcodeFieldClone.find('input');

            $nlPostcodeInput
                .attr('id', flektoNlZipPrefix + '_input_' + currentTimestamp)
                .attr('name', flektoNlZipPrefix + '_input')
                .attr('placeholder', translations.flekto_nl_zip_placeholder)
                .removeAttr('data-bind')
                .prop('disabled', false)
                .addClass(flektoNlZipPrefix + '_input')
                .val('');

            $nlPostcodeInput
                .on('keyup', this.getNlPostcodeAddressDebounced.bind(this))
                .on('blur', this.getNlPostcodeAddress.bind(this))
                .on('focus', this.updateFocusForm.bind(this, $nlPostcodeInput[0]));
        },

        hideNlPostcodeWatcher: function() {

            const $nlPostcodeField = $(fieldsScope).find('.flekto_nl_zip').hide();

            $nlPostcodeField.find('input').val('');
            this.logDebug('hideNlPostcodeWatcher');
            this.toggleFields(true);
        },

        getNlPostcodeAddressDebounced: function (event) {

            this.logDebug('getNlPostcodeAddressDebounced');

            clearTimeout(this.lookupTimeout);
            this.lookupTimeout = setTimeout(this.getNlPostcodeAddress.bind(this), nlPostcodeLookupDelay, event);
        },

        getNlPostcodeAddress: function (event) {

            this.logDebug('getNlPostcodeAddress', event);

            if (event.target.value.trim().toLowerCase() === nlPostcodeLookupValue)
            {
                return; // Lookup value unchanged, abort.
            }

            const that = this,
                nlPostcodeInput = event.target,
                regex = /([1-9][0-9]{3}\s?[a-z]{2})\s?(\d+.*)/i,
                addressData = nlPostcodeInput.value.match(regex),
                warningClassName = 'flekto_nl_zip_input-warning',
                loadingClassName = 'flekto_nl_zip_input-loading';

            nlPostcodeLookupValue = nlPostcodeInput.value.trim().toLowerCase();

            this.resetInputAddress();

            if (!addressData || addressData.length < 3) {

                // No postcode and house number found
                if (nlPostcodeInput.value.length > 7 || nlPostcodeInput !== document.activeElement) {
                    $(fieldsScope).find('.' + warningClassName).remove();
                    $('<span>', {class: warningClassName, text: translations.flekto_nl_zip_warning}).insertAfter(nlPostcodeInput);
                }

                return;
            }

            nlPostcodeInput.classList.add(loadingClassName);

            const url = settings.base_url + 'rest/V1/flekto/postcode-international/nlzipcode/' + addressData[1] + '/' + addressData[2];

            $.get(url, function (response) {

                const result = response[0],
                    houseNumberAdditionsClassName = 'flekto_nl_zip_houseNumberAdditions';

                $(fieldsScope).find('.' + warningClassName).remove();

                if (result.error && result.message_details) {
                    $(fieldsScope).find('.' + houseNumberAdditionsClassName).remove();
                    $('<span>', {class: warningClassName, text: result.message_details}).insertAfter(nlPostcodeInput);
                    return;
                }

                const responseData = result.response,
                    additions = responseData.houseNumberAdditions;

                that.logDebug('Response data: ', responseData);

                that.setInputAddress(responseData);
                $(fieldsScope).find('.' + houseNumberAdditionsClassName).remove();

                if (additions.length > 1) {

                    that.logDebug('Housenumber additions: ', additions);

                    const selectElement = $('<select>', {name: houseNumberAdditionsClassName, class: houseNumberAdditionsClassName});

                    for (let i = 0, len = additions.length; i < len; i++)
                    {
                        selectElement.append($('<option>', {value: additions[i], text: additions[i]}));
                    }

                    selectElement.on('change', function () {
                        that.setInputAddress(Object.create(responseData, {houseNumberAddition: {value: this.value}}));
                    });

                    selectElement.insertAfter(nlPostcodeInput);
                }
            }).always(function () {

                nlPostcodeInput.classList.remove(loadingClassName);
                that.toggleFields(true);

            });

        },

        initIntlAutocomplete: function () {

            this.logDebug('initIntlAutocomplete');

            if (intlAutocompleteInstance !== null)
            {
                return intlAutocompleteInstance.setCountry(elements.country.value);
            }

            const that = this;

            intlAutocompleteInstance = new PostcodeNl.AutocompleteAddress(elements.street, {
                autocompleteUrl: settings.base_url + 'rest/V1/flekto/postcode-international/autocomplete',
                addressDetailsUrl: settings.base_url + 'rest/V1/flekto/postcode-international/getdetails',
                context: elements.country.value,
            });

            elements.street.addEventListener('autocomplete-select', eventHandlers.autocompleteSelect = function (e) {

                if (e.detail.precision === 'Address') {

                    intlAutocompleteInstance.getDetails(e.detail.context, function (result) {
                        const address = result[0].response.address;

                        that.setInputAddress({
                            street: address.street,
                            houseNumber: address.buildingNumber,
                            houseNumberAddition: address.buildingNumberAddition,
                            city: address.locality,
                            postcode: address.postcode,
                        });
                    });

                    that.toggleFields(true);
                }
            });

            elements.street.addEventListener('autocomplete-search', eventHandlers.autocompleteSearch = this.resetInputAddress);
        },

        hideIntlAutocomplete: function () {
            if (intlAutocompleteInstance === null)
            {
                return;
            }

            elements.street.removeEventListener('autocomplete-select', eventHandlers.autocompleteSelect);
            elements.street.removeEventListener('autocomplete-search', eventHandlers.autocompleteSearch);
            intlAutocompleteInstance.destroy();
            intlAutocompleteInstance = null;
        },

        setInputAddress: function (response) {

            const addressString = response.street + ' ' + response.houseNumber + (' ' + (response.houseNumberAddition ? response.houseNumberAddition : ''));

            this.logDebug('AddressString: ' + addressString);

            if (elements.houseNumber !== null) {
                $(elements.street).val(response.street).change();

                if (elements.houseNumberAddition !== null) {
                    $(elements.houseNumber).val(response.houseNumber).change();
                    $(elements.houseNumberAddition).val(response.houseNumberAddition ? response.houseNumberAddition : '').change();

                } else {
                    $(elements.houseNumber).val(response.houseNumber + ((response.houseNumberAddition ? ' ' + response.houseNumberAddition : ''))).change();
                }

            } else {
                $(elements.street).val(addressString).change();
            }

            if (response.city !== null) {
                $(elements.city).val(response.city).change();
            }

            if (response.postcode !== null) {
                $(elements.postcode).val(response.postcode).change();
            }

            if (response.province !== null) {
                $(elements.province).val(response.province).change();
            }
        },

        resetInputAddress: function () {

            $(elements.street).not(selectors.intlAutcomplete).val('');
            elements.houseNumber.value = '';
            elements.houseNumberAddition.value = '';
            elements.postcode.value = '';
            elements.city.value = '';
            elements.province.value = '';
        },

        toggleFields: function (state) {

            if (settings.show_hide_address_fields === 'show') {
                return;
            }

            const $fields = $(fieldsScope).find('[name^="street["][name$="]"], [name="city"], [name="postcode"], [name="region"]').not(selectors.intlAutcomplete);

            this.logDebug('Toggle address fields: ', state, 'fields: ', $fields);

            if (settings.show_hide_address_fields === 'disable') {
                $fields.prop('disabled', !state);
            } else if (settings.show_hide_address_fields === 'hide') {
                $fields.closest('div.field').toggle(state);
                $fields.closest('fieldset.field').toggle(state);
            }
        },

        logDebug: function () {
            if (settings.debug) {
                console.log.apply(null, arguments);
            }
        },

    });

});
