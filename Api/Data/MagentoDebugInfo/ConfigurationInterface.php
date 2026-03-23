<?php

namespace PostcodeEu\AddressValidation\Api\Data\MagentoDebugInfo;

interface ConfigurationInterface
{
    /**
     * @return string
     */
    public function getKey(): string;

    /**
     * @return string
     */
    public function getSecret(): string;
}
