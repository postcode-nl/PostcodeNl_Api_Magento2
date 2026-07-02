<?php

namespace PostcodeEu\AddressValidation\Service\Exception;

class UnexpectedException extends ClientException
{
    /** @var int HTTP status code */
    protected int $code = 500;
}
