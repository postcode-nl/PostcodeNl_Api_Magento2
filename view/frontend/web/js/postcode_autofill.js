define([
    'Magento_Ui/js/form/components/group',
    'jquery',
    'Flekto_Postcode/js/postcode_autofill_client',
    'uiRegistry',
    'domReady!'
], function (Abstract, $, postcodenl, registry) {

    return Abstract.extend({

        streetFieldSelector: "[name^='street[']",
        primaryStreetFieldSelector: "[name^='street[']:first",
        countryFieldSelector: "[name='country_id']",
        autocomplete: null,
        currentStreetElement: null,
        currentHouseNumElement: null,
        currentHouseNumAdditionElement: null,
        currentCountryElement: null,
        fieldsScope: null,
        internationalAutocompleteActive: false,
        lookupTimeout: 0,
        enableDisableFieldsNl: ['[name^="street["][name$="]"]', "[name='city']", "[name='postcode']", "[name='region']"],
        enableDisableFieldsInt: ['[name^="street["][name$="]"]:not(:first)', "[name='city']", "[name='postcode']", "[name='region']"],
        nlPostcodeInputCloneFrom: 'div[name$=".postcode"]',
        nlPostcodeInputCloneInsertAfter: 'div[name$=".country_id"]',


        initialize: function () {

            this._super();
            var that = this;

            if (that.getSettings().enabled == 1) {

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

                var that = this;

                $.each(window.postcodenlConfig, function(key, value) {
                    that.logDebug('Config override: ', key, value);
                    that[key] = value;
                });
            }
        },


        initWatcher: function () {

            var that = this;

            $(document).ready(function(e){

                $(document).on('change', that.countryFieldSelector, function() {

                    var dropdownEl = this;

                    // make sure field is set.
                    setTimeout(function() {

                        that.logDebug(dropdownEl);
                        that.logDebug('country changed');
                        that.logDebug("nl_input_behavior: ", that.getSettings().nl_input_behavior);
                        that.logDebug("selected country: ", $("[name='country_id']:visible :selected ").text());
                        that.logDebug($(dropdownEl).val());

                        that.updateFocusForm(dropdownEl);

                        var countriesArr = JSON.parse(that.getSettings().supported_countries);
                        that.internationalAutocompleteActive = false;
                        that.disableInternationalAutocomplete();
                        that.disableNlPostcodeWatcher();

                        document.addEventListener('autocomplete-xhrerror', function (e) {
                            that.logDebug('XHR Error!', e);
                            that.enableDisableFields('show');
                        });

                        if (that.getSettings().nl_input_behavior == 'zip_house' && $(dropdownEl).val() == 'NL') {

                            that.logDebug('zip_house init');
                            that.initNlPostcodeWatcher();
                            that.enableDisableFields('hide');

                        } else {

                            if (jQuery.inArray($(dropdownEl).val(), countriesArr) !== -1) {

                                that.logDebug('free input init');

                                that.enableInternationalAutocomplete();
                                that.initFreeInputWatcher();
                                that.enableDisableFields('hide');

                            }
                        }

                    }, 200);

                });
            });

        },


        updateFocusForm: function(el) {

            var form = $(el).closest('form');
            this.logDebug('updateFocusForm', form);

            if (form) {
                this.fieldsScope = form;
                this.currentStreetElement = $(form).find(this.streetFieldSelector).get(0);
                this.currentHouseNumElement = $(form).find(this.streetFieldSelector).get(1);
                this.currentHouseNumAdditionElement = $(form).find(this.streetFieldSelector).get(2);
                this.currentCountryElement = $(form).find(this.countryFieldSelector).get(0);
            }
        },


        initNlPostcodeWatcher: function() {

            this.logDebug('initNlPostcodeWatcher');

            if (this.fieldsScope.find('.flekto_nl_zip').length == 0) {

                var currentTimestamp = $.now();
                var that = this;
                var lookupTimeout = 0;

                this.fieldsScope.find(this.nlPostcodeInputCloneFrom)
                    .clone()
                    .removeAttr('data-bind')
                    .prop('id', 'flekto_nl_zip_'+currentTimestamp)
                    .prop('name', 'flekto_nl_zip_'+currentTimestamp)
                    .removeClass('_error')
                    .addClass('flekto_nl_zip')
                    .insertAfter(this.fieldsScope.find(this.nlPostcodeInputCloneInsertAfter));

                this.fieldsScope.find('.flekto_nl_zip').find('.warning').remove();
                this.fieldsScope.find('.flekto_nl_zip').find('.field-error').remove();
                this.fieldsScope.find('.flekto_nl_zip').find('span').text(this.getTranslations().flekto_nl_zip_label);

                var inputEl = this.fieldsScope.find('#flekto_nl_zip_'+currentTimestamp)
                    .find('input')
                    .attr('id', 'flekto_nl_zip_input_'+currentTimestamp)
                    .attr('name', 'flekto_nl_zip_input')
                    .attr('placeholder', this.getTranslations().flekto_nl_zip_placeholder)
                    .removeAttr('data-bind')
                    .prop('disabled', false)
                    .addClass('flekto_nl_zip_input')
                    .val('');

                $(inputEl).on('keyup', {scope: this}, this.delayGetNlPostcodeAddress);
                $(inputEl).on('blur', {scope: this}, this.getNlPostcodeAddress);
                $(inputEl).on('focus', function() {
                    that.updateFocusForm(inputEl);
                });
            }
        },


        disableNlPostcodeWatcher: function() {
            this.fieldsScope.find('.flekto_nl_zip').remove();
            this.logDebug('disableNlPostcodeWatcher');

            this.enableDisableFields('show');
        },


        delayGetNlPostcodeAddress: function (event) {

            var that = event.data.scope;

            that.logDebug('delayGetNlPostcodeAddress');

            clearTimeout(that.lookupTimeout);
            that.lookupTimeout = setTimeout(function() {
                that.getNlPostcodeAddress(event);
            }, 750, event);
        },


        getNlPostcodeAddress: function(event) {

            var that = event.data.scope;

            that.logDebug('getNlPostcodeAddress', event);

            var input = jQuery(event.target);
            var addressContainer = that.fieldsScope;
            var query = input.val();
            var regex = /([1-9][0-9]{3}\s?[a-z]{2})\s?(\d+.*)/i;
            var addressData = query.match(regex);

            if (!addressData || addressData.length < 3) {

                // No postcode and house number found
                if (query.length > 7 || !input.is(':focus')) {
                    $(addressContainer).find('.postcodenl-address-autocomplete-warning').remove();
                    input.after('<span class="postcodenl-address-autocomplete-warning">' + that.getTranslations().flekto_nl_zip_warning + '</span>');
                }
                return;
            }

            input.addClass('postcodenl-address-autocomplete-loading');

            var postcode = addressData[1];
            var houseNumber = addressData[2];
            jQuery.get(that.getSettings().base_url+'rest/V1/flekto/postcode-international/nlzipcode/' + postcode + '/' + houseNumber, function(response) {

                response = response[0];

                $('.postcodenl-address-autocomplete-warning').remove();

                if (response.error && response.message_details) {
                    $(that.fieldsScope).find('.flekt_nl_zip_houseNumberAdditions').remove();
                    input.after('<span class="postcodenl-address-autocomplete-warning">' + response.message_details + '</span>');
                    return;
                }

                var responseData = response.response;
                that.logDebug(responseData);

                var addressString = responseData.street + ' ' + responseData.houseNumber + (' ' + (responseData.houseNumberAddition ? responseData.houseNumberAddition : ''));
                that.setInputAddress(
                        {'street': responseData.street,
                         'houseNumber': responseData.houseNumber,
                         'houseNumberAddition': responseData.houseNumberAddition,
                         'city': responseData.city,
                         'province': responseData.province,
                         'postcode': responseData.postcode,
                         }
                    );

                if (responseData.houseNumberAdditions.length > 1) {

                    that.logDebug(responseData.houseNumberAdditions);

                    $(that.fieldsScope).find('.flekt_nl_zip_houseNumberAdditions').remove();
                    var appendHouseNumberAdditions = '<select name="flekt_nl_zip_houseNumberAdditions" class="flekt_nl_zip_houseNumberAdditions">';

                    jQuery.each(responseData.houseNumberAdditions, function(i, obj) {
                        appendHouseNumberAdditions += '<option value="' + obj + '">' + obj + '</option>';
                    });

                    appendHouseNumberAdditions += '</select>';
                    input.after(appendHouseNumberAdditions);

                    $(that.fieldsScope).find('.flekt_nl_zip_houseNumberAdditions').on('change', function() {
                        that.setInputAddress(
                            {'street': responseData.street,
                             'houseNumber': responseData.houseNumber,
                             'houseNumberAddition': this.value,
                             'city': responseData.city,
                             'province': responseData.province,
                             'postcode': responseData.postcode,
                             }
                        );
                    });

                } else {
                    $(that.fieldsScope).find('.flekt_nl_zip_houseNumberAdditions').remove();
                }

                that.enableDisableFields('show');

            }).always(function() {

                input.removeClass('postcodenl-address-autocomplete-loading');
                that.enableDisableFields('show');

            });

        },


        initFreeInputWatcher: function() {

            this.logDebug('initFreeInputWatcher');

            var that = this;
            $(this.fieldsScope).on('focus', this.primaryStreetFieldSelector, function() {

                that.updateFocusForm(this);

                if ($(that.fieldsScope).data('int-autocomplete') != '1') {
                    that.disableInternationalAutocomplete();
                    return;
                }

                that.initPostcodeAutocompleteLibrary();
            });
        },


        initPostcodeAutocompleteLibrary: function () {

            this.logDebug('initPostcodeAutocompleteLibrary');

            var that = this;
            this.enableInternationalAutocomplete();

            $(this.currentStreetElement).each(function(i, obj) {

                if (!$(this).hasClass('postcodenl-autocomplete-address-input')) {

                    that.autocomplete = new PostcodeNl.AutocompleteAddress($(this).get(0), {
                        autocompleteUrl: that.getSettings().base_url+'rest/V1/flekto/postcode-international/autocomplete',
                        addressDetailsUrl: that.getSettings().base_url+'rest/V1/flekto/postcode-international/getdetails',
                        context: $(that.currentCountryElement).children('option:selected').val()
                    });


                    $(this).get(0).addEventListener('autocomplete-select', function (e) {

                        if (e.detail.precision === 'Address') {

                            that.autocomplete.getDetails(e.detail.context, function (result) {
                                result = result[0].response;
                                that.setInputAddress(
                                    {'street': result.address['street'],
                                     'houseNumber': result.address['buildingNumber'],
                                     'houseNumberAddition': result.address['buildingNumberAddition'],
                                     'city': result.address['locality'],
                                     'postcode': result.address['postcode']
                                     }
                                );
                            });

                            that.enableDisableFields('show');
                        }
                    });

                } else {

                    if (that.autocomplete.options.context != $(that.currentCountryElement).children('option:selected').val().toLowerCase()) {
                        that.autocomplete.setCountry($(that.currentCountryElement).children('option:selected').val());
                    }
                }
            });
        },


        setInputAddress: function(response) {

            var addressString = response.street + ' ' + response.houseNumber + (' ' + (response.houseNumberAddition ? response.houseNumberAddition : ''));

            this.logDebug('AddressString: ' + addressString);

            if (this.currentHouseNumElement != null) {
                $(this.currentStreetElement).val(response.street).change();

                if (this.currentHouseNumAdditionElement != null) {
                    $(this.currentHouseNumElement).val(response.houseNumber).change();
                    $(this.currentHouseNumAdditionElement).val(response.houseNumberAddition ? response.houseNumberAddition : '').change();

                } else {
                    $(this.currentHouseNumElement).val(response.houseNumber + ((response.houseNumberAddition ? ' ' + response.houseNumberAddition : ''))).change();
                }

            } else {
                $(this.currentStreetElement).val(addressString).change();
            }

            if (response.city !== null) {
                $(this.fieldsScope).find('[name="city"]').val(response.city).change();
            }

            if (response.postcode !== null) {
                $(this.fieldsScope).find('[name="postcode"]').val(response.postcode).change();
            }

            if (response.province !== null) {
                $(this.fieldsScope).find('[name="region"]').val(response.province).change();
            }
        },


        preventDefaultFunc: function (event) {
            event = event || null;
            if (event !== null) {
                event.preventDefault();
            }
        },


        enableInternationalAutocomplete: function () {

            this.logDebug('enableInternationalAutocomplete');
            this.currentStreetElement.removeEventListener('autocomplete-menubeforeopen', this.preventDefaultFunc);
            this.currentStreetElement.removeEventListener('autocomplete-search', this.preventDefaultFunc);
            $(this.fieldsScope).data('int-autocomplete', '1');
        },


        disableInternationalAutocomplete: function () {

            this.logDebug('disableInternationalAutocomplete');
            this.currentStreetElement.addEventListener('autocomplete-menubeforeopen', this.preventDefaultFunc, false);
            this.currentStreetElement.addEventListener('autocomplete-search', this.preventDefaultFunc, false);
            $(this.fieldsScope).data('int-autocomplete', '0');
        },


        enableDisableFields: function(endis) {

            if (this.getSettings().show_hide_address_fields != 'show') {

                var fields = this.enableDisableFieldsInt;
                if (this.fieldsScope.find('.flekto_nl_zip').length || endis == 'show') {
                    fields = this.enableDisableFieldsNl;
                }

                if (endis == 'show') {
                    this.logDebug('Show input fields', fields.toString());
                } else {
                    this.logDebug('Hide input fields', fields.toString());
                }

                var that = this;
                jQuery.each(fields, function(index, selector) {

                    if (that.getSettings().show_hide_address_fields == 'disable') {
                        $(that.fieldsScope).find(selector).prop("disabled", ((endis == 'show') ? false : true));

                    } else if (that.getSettings().show_hide_address_fields == 'hide') {

                        if (endis == 'show') {
                            $(that.fieldsScope).find(selector).closest("div.field").show();
                            $(that.fieldsScope).find(selector).closest("fieldset.field").show();

                        } else {

                            $(that.fieldsScope).find(selector).closest("div.field").hide();
                            if (that.fieldsScope.find('.flekto_nl_zip').length) {
                                $(that.fieldsScope).find(selector).closest("fieldset.field").hide();
                            }
                        }
                    }


                });

            }
        },


        getSettings: function () {
            var settings = window.checkoutConfig.flekto_postcode.settings;
            return settings;
        },


        getTranslations: function () {
            return this.getSettings().translations;
        },


        logDebug: function(...params) {
            if (this.getSettings().debug) {
                console.log(...params);
            }
        }

    });

});