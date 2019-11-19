
define([
    'Magento_Ui/js/form/components/group',
    'jquery',
    'Flekto_Postcode/js/postcode_autofill_client',
    'uiRegistry'
], function (Abstract, $, postcodenl, registry) {
    'use strict';

    return Abstract.extend({

        streetFieldSelector: "input[name='street[0]']",
        autocomplete: null,
        currentStreetElement: null,
        currentCountryElement: null,
        fieldsScope: null,


        initObservable: function () {
            this._super();
            return this;
        },

        initialize: function () {

            this._super();
            var that = this;

            if (that.getSettings().enabled == 1) {

                registry.async(this.provider)(function () {

                    that.initModules();

                    var found = false;
                    $(window).on('hashchange', function(){

                        var exitCondition = setInterval(function() {

                                if ($(that.streetFieldSelector).length == 2 && $("#checkout-payment-method-load [name='country_id']").val()) {

                                    that.fieldsScope = "#checkout-payment-method-load";
                                    that.currentStreetElement = $(that.fieldsScope+" input[name='street[0]']").get(0);
                                    that.currentCountryElement = $(that.fieldsScope+" [name='country_id']").get(0);

                                    that.initPostcodeAutocompleteLibrary();
                                    that.countryObserver(that.autocomplete);
                                    clearInterval(exitCondition);
                                }
                        }, 100);
                    });

                    var exitCondition = setInterval(function() {

                        if ($(that.streetFieldSelector).length && $("#opc-new-shipping-address [name='country_id']").val()) {
                            that.fieldsScope = "#opc-new-shipping-address";
                            that.currentStreetElement = $(that.fieldsScope+" input[name='street[0]']").get(0);
                            that.currentCountryElement = $(that.fieldsScope+" [name='country_id']").get(0);

                            that.initPostcodeAutocompleteLibrary();
                            that.countryObserver(that.autocomplete);
                            clearInterval(exitCondition);
                            found = true;
                        }
                    }, 100);

                    if (found === false) {
                        var exitCondition = setInterval(function() {

                            if ($(that.streetFieldSelector).length && $("#checkout-step-shipping [name='country_id']").val()) {
                                that.fieldsScope = "#checkout-step-shipping";
                                that.currentStreetElement = $(that.fieldsScope+" input[name='street[0]']").get(0);
                                that.currentCountryElement = $(that.fieldsScope+" [name='country_id']").get(0);

                                that.initPostcodeAutocompleteLibrary();
                                that.countryObserver(that.autocomplete);
                                clearInterval(exitCondition);
                            }
                        }, 100);
                    }


                });
            }

            return this;
        },

        initPostcodeAutocompleteLibrary: function () {

            var that = this;

            $('[name="street[0]"]').each(function(i, obj) {

                if (!$(this).hasClass('postcodenl-autocomplete-address-input')) {

                    that.autocomplete = new PostcodeNl.AutocompleteAddress($(this).get(0), {
                    	autocompleteUrl: '/rest/default/V1/flekto/postcode-international/autocomplete',
                    	addressDetailsUrl: '/rest/default/V1/flekto/postcode-international/getdetails',
                        context: $(that.currentCountryElement).children('option:selected').val()
                    });

                    $(this).get(0).addEventListener('autocomplete-select', function (e) {

                    	if (e.detail.precision === 'Address')
                    	{
                    		that.autocomplete.getDetails(e.detail.context, function (result) {
                                result = result[0].response;
                                $(that.fieldsScope+' [name="city"]').val(result.address['locality']).change();
                                $(that.fieldsScope+' [name="postcode"]').val(result.address['postcode']).change();
                                $(that.currentStreetElement).val(result.address['street']+" "+result.address['building']).change();
                                that.countryChanged($(that.currentCountryElement).children('option:selected').val());
                                //debugger;
                    		});
                    	}
                    });
                }
            });

            document.addEventListener('autocomplete-xhrerror', function (e) {
                that.enableFields();
            });
        },

        preventDefaultFunc: function (event) {
            event = event || null;
            if (event !== null) {
                event.preventDefault();
            }
        },

        disableFields: function () {
            $(this.fieldsScope+' [name="city"]').prop('disabled', true);
            $(this.fieldsScope+' [name="postcode"]').prop('disabled', true);

            this.currentStreetElement.removeEventListener('autocomplete-menubeforeopen', this.preventDefaultFunc);
            this.currentStreetElement.removeEventListener('autocomplete-search', this.preventDefaultFunc);
        },

        enableFields: function () {
            $(this.fieldsScope+' [name="city"]').prop('disabled', false);
            $(this.fieldsScope+' [name="postcode"]').prop('disabled', false);

            this.currentStreetElement.addEventListener('autocomplete-menubeforeopen', this.preventDefaultFunc, false);
            this.currentStreetElement.addEventListener('autocomplete-search', this.preventDefaultFunc, false);
        },

        countryObserver: function (autocomplete) {

            var that = this;

            this.countryChanged();

            $(this.currentCountryElement).on('change', function() {

                that.countryChanged(this.value);
                that.autocomplete.setCountry(this.value);

            });
        },

        countryChanged: function (value) {

            value = value || $(this.currentCountryElement).children('option:selected').val();
            var countriesArr = JSON.parse(this.getSettings().supported_countries);
            if (jQuery.inArray(value, countriesArr) !== -1) {
                this.disableFields();
            } else {
                this.enableFields();
            }
        },

        getSettings: function () {
            var settings = window.checkoutConfig.flekto_postcode.settings;
            return settings;
        },
    });

});