<?php

namespace PostcodeEu\AddressValidation\Api\Data\MagentoDebugInfo;

interface MagentoModuleInterface
{
    /**
     * Get module name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get module setup version
     *
     * @return string
     */
    public function getSetupVersion(): string;
}
