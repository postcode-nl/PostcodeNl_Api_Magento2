
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

        initialize: function () {

            this._super();
            var that = this;

            if (that.getSettings().enabled == 1) {

                registry.async(this.provider)(function () {

                    that.initModules();
                    that.initWatcher();

                });
            }

            return this;
        },

        initWatcher: function () {

            var that = this;

            $('body').on('focus', "[name='street[0]']", function() {

                var form = this.closest("form");
                if (form) {

                    that.fieldsScope = form;
                    that.currentStreetElement = $(form).find(that.streetFieldSelector).get(0);
                    that.currentCountryElement = $(form).find("[name='country_id']").get(0);

                    that.initPostcodeAutocompleteLibrary();
                    that.countryObserver(that.autocomplete);
                }
            });

        },

        initPostcodeAutocompleteLibrary: function () {

            var that = this;

            $(that.streetFieldSelector).each(function(i, obj) {

                if (!$(this).hasClass('postcodenl-autocomplete-address-input')) {

                    that.autocomplete = new PostcodeNl.AutocompleteAddress($(this).get(0), {
                    	autocompleteUrl: '/rest/V1/flekto/postcode-international/autocomplete',
                    	addressDetailsUrl: '/rest/V1/flekto/postcode-international/getdetails',
                        context: $(that.currentCountryElement).children('option:selected').val()
                    });

                    $(this).get(0).addEventListener('autocomplete-select', function (e) {

                    	if (e.detail.precision === 'Address')
                    	{
                    		that.autocomplete.getDetails(e.detail.context, function (result) {
                                result = result[0].response;
                                $(that.fieldsScope).find('[name="city"]').val(result.address['locality']).change();
                                $(that.fieldsScope).find('[name="postcode"]').val(result.address['postcode']).change();
                                $(that.currentStreetElement).val(result.address['street']+" "+result.address['building']).change();
                                that.countryChanged($(that.currentCountryElement).children('option:selected').val());
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
            $(this.fieldsScope).find('[name="city"]').prop('disabled', true);
            $(this.fieldsScope).find('[name="postcode"]').prop('disabled', true);

            this.currentStreetElement.removeEventListener('autocomplete-menubeforeopen', this.preventDefaultFunc);
            this.currentStreetElement.removeEventListener('autocomplete-search', this.preventDefaultFunc);
        },

        enableFields: function () {
            $(this.fieldsScope).find('[name="city"]').prop('disabled', false);
            $(this.fieldsScope).find('[name="postcode"]').prop('disabled', false);

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