<?php

namespace PostcodeEu\AddressValidation\Api;

interface PostcodeModelInterface
{

    /**
     * @access public
     * @param string $context
     * @param string $term
     * @return \PostcodeEu\AddressValidation\Api\Data\AutocompleteInterface
     */
    public function getAddressAutocomplete(string $context, string $term): \PostcodeEu\AddressValidation\Api\Data\AutocompleteInterface;

    /**
     * @access public
     * @param string $context
     * @return string[][]
     */
    public function getAddressDetails(String $context): array;

    /**
     * @access public
     * @param string $context
     * @param string $dispatchCountry
     * @return string[][]
     */
    public function getAddressDetailsCountry(String $context, String $dispatchCountry): array;

    /**
     * @access public
     * @param string $zipCode
     * @param string $houseNumber
     * @return string[][]
     */
    public function getNlAddress(String $zipCode, String $houseNumber): array;

    /**
     * @access public
     * @param string $country
     * @param string|null $postcode
     * @param string|null $locality
     * @param string|null $street
     * @param string|null $building
     * @param string|null $region
     * @param string|null $streetAndBuilding
     * @return string[][]
     */
    public function validateAddress(
        string $country,
        ?string $postcode = null,
        ?string $locality = null,
        ?string $street = null,
        ?string $building = null,
        ?string $region = null,
        ?string $streetAndBuilding = null
    ): array;
}
