<?php

namespace Flekto\Postcode\Model;

use Flekto\Postcode\Helper\ApiClientHelper;

class PostcodeModel
{

    /**
     * apiClientHelper
     *
     * @var mixed
     * @access protected
     */
    protected $apiClientHelper;


    /**
     * __construct function.
     *
     * @access public
     * @param apiClientHelper $apiClientHelper
     * @return void
     */
    public function __construct(ApiClientHelper $apiClientHelper) {
        $this->apiClientHelper = $apiClientHelper;
    }


    /**
     * getAddressAutocomplete function.
     *
     * @access public
     * @param $session $context
     * @param $session $term
     * @param $session $language
     * @return Json
     */
    public function getAddressAutocomplete(String $context, String $term)
    {
        $result = $this->apiClientHelper->getAddressAutocomplete($context, $term);
        return [$result];
    }


    /**
     * getAddressDetails function.
     *
     * @access public
     * @param String $context
     * @return void
     */
    public function getAddressDetails(String $context)
    {
        $result = $this->apiClientHelper->getAddressDetails($context);
        return [$result];
    }


    /**
     * getAddressDetailsCountry function.
     *
     * @access public
     * @param String $context
     * @param String $dispatchCountry
     * @return void
     */
    public function getAddressDetailsCountry(String $context, String $dispatchCountry)
    {
        $result = $this->apiClientHelper->getAddressDetails($context, $dispatchCountry);
        return [$result];
    }

}
