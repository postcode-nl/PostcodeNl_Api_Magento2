<?php

namespace PostcodeEu\AddressValidation\HTTP\Client;

/**
 * Extend Curl class to isolate configured curl options from the rest of the application.
 */
class Curl extends \Magento\Framework\HTTP\Client\Curl
{
    /**
     * Request timeout
     * @var int type
     */
    protected $_timeout = 30;
}
