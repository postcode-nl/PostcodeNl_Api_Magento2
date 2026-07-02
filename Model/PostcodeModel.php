<?php

namespace PostcodeEu\AddressValidation\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Exception as WebapiException;
use PostcodeEu\AddressValidation\Api\Data\Autocomplete as AutocompleteData;
use PostcodeEu\AddressValidation\Api\Data\AutocompleteInterface as AutocompleteDataInterface;
use PostcodeEu\AddressValidation\Api\PostcodeModelInterface;
use PostcodeEu\AddressValidation\Helper\ApiClientHelper;
use PostcodeEu\AddressValidation\Service\CsrfValidator;

class PostcodeModel implements PostcodeModelInterface
{
    /** @var ApiClientHelper */
    protected ApiClientHelper $_apiClientHelper;
    /** @var CsrfValidator */
    protected CsrfValidator $_csrfValidator;

    /**
     * Constructor
     *
     * @access public
     * @param ApiClientHelper $apiClientHelper
     * @param CsrfValidator $csrfValidator
     * @return void
     */
    public function __construct(
        ApiClientHelper $apiClientHelper,
        CsrfValidator $csrfValidator
    ) {
        $this->_apiClientHelper = $apiClientHelper;
        $this->_csrfValidator = $csrfValidator;
    }

    /**
     * @inheritdoc
     */
    public function getAddressAutocomplete(string $context, string $term): AutocompleteDataInterface
    {
        $this->_validateRequest();

        $result = $this->_apiClientHelper->getAddressAutocomplete($context, $term);
        return new AutocompleteData($result);
    }

    /**
     * @inheritdoc
     */
    public function getAddressDetails(string $context): array
    {
        $this->_validateRequest();

        $result = $this->_apiClientHelper->getAddressDetails($context);
        return [$result];
    }

    /**
     * @inheritdoc
     */
    public function getAddressDetailsCountry(string $context, string $dispatchCountry): array
    {
        $this->_validateRequest();

        $result = $this->_apiClientHelper->getAddressDetails($context, $dispatchCountry);
        return [$result];
    }

    /**
     * @inheritdoc
     */
    public function getNlAddress(string $zipCode, string $houseNumber): array
    {
        $this->_validateRequest();

        $result = $this->_apiClientHelper->getNlAddress($zipCode, $houseNumber);
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
        $this->_validateRequest();

        $result = $this->_apiClientHelper->validateAddress(...func_get_args());
        return [$result];
    }

    private function _validateRequest(): void
    {
        try {
            $this->_csrfValidator->validate();
        } catch (LocalizedException $e) {
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_FORBIDDEN);
        }
    }
}
