<?php

namespace PostcodeEu\AddressValidation\Api\Data;

interface MagentoDebugInfoInterface
{
    /**
     * @return string
     */
    public function getModuleVersion(): string;

    /**
     * @return string
     */
    public function getMagentoVersion(): string;

    /**
     * @return string
     */
    public function getClient(): string;

    /**
     * @return string
     */
    public function getSession(): string;

    /**
     * @return PostcodeEu\AddressValidation\Api\Data\MagentoDebugInfo\ConfigurationInterface
     */
    public function getConfiguration(): MagentoDebugInfo\ConfigurationInterface;

    /**
     * @return PostcodeEu\AddressValidation\Api\Data\MagentoDebugInfo\MagentoModuleInterface[]
     */
    public function getModules(): array;
}
