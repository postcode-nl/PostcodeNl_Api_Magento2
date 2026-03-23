<?php

namespace PostcodeEu\AddressValidation\Model;

use PostcodeEu\AddressValidation\Helper\ApiClientHelper;
use PostcodeEu\AddressValidation\Api\PostcodeModelInterface;
use PostcodeEu\AddressValidation\Api\Data\Autocomplete as AutocompleteData;
use PostcodeEu\AddressValidation\Api\Data\AutocompleteInterface as AutocompleteDataInterface;

class PostcodeModel implements PostcodeModelInterface
{

    /**
     * @var ApiClientHelper
     */
    protected $apiClientHelper;

    /**
     * __construct function.
     *
     * @access public
     * @param ApiClientHelper $apiClientHelper
     * @return void
     */
    public function __construct(ApiClientHelper $apiClientHelper)
    {
        $this->apiClientHelper = $apiClientHelper;
    }

    /**
     * @inheritdoc
     */
    public function getAddressAutocomplete(string $context, string $term): AutocompleteDataInterface
    {
        $result = $this->apiClientHelper->getAddressAutocomplete($context, $term);
        return new AutocompleteData($result);
    }

    /**
     * @inheritdoc
     */
    public function getAddressDetails(string $context): array
    {
        $result = $this->apiClientHelper->getAddressDetails($context);
        return [$result];
    }

    /**
     * @inheritdoc
     */
    public function getAddressDetailsCountry(string $context, string $dispatchCountry): array
    {
        $result = $this->apiClientHelper->getAddressDetails($context, $dispatchCountry);
        return [$result];
    }

    /**
     * @inheritdoc
     */
    public function getNlAddress(string $zipCode, string $houseNumber): array
    {
        $result = $this->apiClientHelper->getNlAddress($zipCode, $houseNumber);
        return [$result];
    }

    /**
     * @inheritdoc
     */
    public function validateAddress(
        string $country,
        ?string $postcode = null,
        ?string $locality = null,
        ?string $street = null,
        ?string $building = null,
        ?string $region = null,
        ?string $streetAndBuilding = null
    ): array {
        $result = $this->apiClientHelper->validateAddress(...func_get_args());
        return [$result];
    }
}
