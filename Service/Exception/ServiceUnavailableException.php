<?php

namespace PostcodeEu\AddressValidation\Service\Exception;

class ServiceUnavailableException extends ClientException
{
    protected $code = 503;
}
