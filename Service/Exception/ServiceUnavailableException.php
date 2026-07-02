<?php

namespace PostcodeEu\AddressValidation\Service\Exception;

class ServiceUnavailableException extends ClientException
{
    /** @var int HTTP status code */
    protected int $code = 503;
}
