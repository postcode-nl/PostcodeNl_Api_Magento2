<?php

namespace Flekto\Postcode\Api;

use Flekto\Postcode\Helper\ApiClientHelper;

interface PostcodeModelInterface
{

    /**
     * @access public
     * @param string $context
     * @param string $term
     * @return Data\AutocompleteInterface
     */
    public function getAddressAutocomplete(string $context, string $term): Data\AutocompleteInterface;


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

}
