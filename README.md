Postcode.eu International Address API module for Magento 2
=============

Adds autocompletion for addresses to the checkout page. Multiple countries are supported using official postal data via the [Postcode.eu](https://postcode.eu) API.

This module has been created by [Postcode.nl](https://postcode.nl) and [Flekto](https://www.flekto.nl).


Postcode.eu Account
=============

A [Postcode.eu account](https://www.postcode.nl/en/services/adresdata/producten-overzicht) is required.
Testing is free. After testing you can choose to purchase a subscription.

Installation instructions
=============

1. Install this component using Composer:

```bash
$ composer require postcode-nl/api-magento2-module
```

2. Upgrade, compile & clear cache:
```bash
$ php bin/magento setup:upgrade
$ php bin/magento setup:di:compile
$ php bin/magento cache:flush
```

## OneStepCheckout.com configuration instructions

1. Go to Stores -> Configuration -> Sales -> Postcode.eu Address API
   1. 'Change address fields position' to 'no'
2. Go to Stores -> Configuration -> Sales -> OneStepCheckout
   1. The fields 'street.0', 'postcode', 'city' and 'region' need to be enabled for the billing **and** shipping fields.
   2. Add 'address_autofill_nl.postcode', 'address_autofill_nl.house_number', 'address_autofill_nl.house_number_select', 'address_autofill_intl' and 'address_autofill_formatted_output' to the billing **and** shipping fields.
   3. Optional: you may need to apply some custom CSS to display the fields correctly. You may set the region field to hidden.
   
License
=============

The code is available under the Simplified BSD License, see the included LICENSE file.

Screenshots
=============

International autocomplete:

![](address-autofill-intl.png)

Dutch address by postcode and house number:

![](address-autofill-nl-house-number-addition.png)

Option to show formatted output:

![](address-autofill-nl-formatted-output.png)
