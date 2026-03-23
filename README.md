![Postcode.eu](media/postcode-eu-logo-gradient.svg)

## International Address API module for Magento 2

Adds autocompletion for addresses to the checkout page. [Multiple countries](https://www.postcode.eu/products/address-api/international) are supported using official postal data via the [Postcode.eu](https://postcode.eu) API.

This module is maintained by [Postcode.eu](https://postcode.eu).

## Postcode.eu account

A [Postcode.eu account](https://account.postcode.eu) is required.
Testing is free. After testing you can choose to purchase a subscription.

## Installation instructions

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

## Screenshots

### International Address API

A single field for autocompletion:

![](media/example-intl-api-be.png)
![](media/example-intl-api-de.png)

To allow users to skip the autocomplete field and manually enter an address, there's an option to add a link to manual address entry:

![](media/example-intl-api-manual-entry-option.png)

### Dutch Postcode API

Get a Dutch address by postcode and house number. In this example asking the user to select from valid house number additions:

![](media/example-nl-api-house-number-addition.png)

A formatted address is shown when the postcode and house number combination is valid (this is the default output option):

![](media/example-nl-api-formatted-output.png)

Other output options are:

* Hide address fields until postcode and house number combination is valid.
* Disable address fields until postcode and house number combination is valid.
* No change; address fields remain visible and editable.

## GraphQL Support

Our module now supports GraphQL, allowing you to query address data via Magento's GraphQL API. This enables integration with headless Magento setups, progressive web applications (PWAs), and other front-end technologies that leverage GraphQL.

## Compatibility

Although we can't guarantee compatibility with other checkout modules, our module works well with most one-step-checkout modules. If you are having issues and think this may be caused by our module, please [contact Postcode.eu](mailto:tech@postcode.nl) and tell us which other module(s) and version(s) are used.

If you found the solution already and have some code to contribute, feel free to open a pull request in this repository.

## Address API documentation

You can find our API documentation at https://developer.postcode.eu/documentation.

## Module Wiki

Instructions for additional configuration and customization can be found on the [wiki pages](https://github.com/postcode-nl/PostcodeNl_Api_Magento2/wiki).

## FAQ and Knowledge Base

* View Frequently Asked Questions at https://www.postcode.eu/#faq.
* For more questions and answers, see https://kb.postcode.eu
* If the above didn't answer your question, [contact us](https://www.postcode.eu/contact).

## License

The code is available under the Simplified BSD License, see the included LICENSE file.
