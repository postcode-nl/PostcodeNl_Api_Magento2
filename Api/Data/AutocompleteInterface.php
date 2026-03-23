<?php

namespace PostcodeEu\AddressValidation\Api\Data;

interface AutocompleteInterface
{
    /**
     * @return PostcodeEu\AddressValidation\Api\Data\Autocomplete\MatchInterface[]
     */
    public function getMatches(): array;

    /**
     * @return string|null
     */
    public function getError(): ?string;

    /**
     * @return string|null
     */
    public function getMessage(): ?string;

    /**
     * @return string|null
     */
    public function getException(): ?string;

    /**
     * @return PostcodeEu\AddressValidation\Api\Data\MagentoDebugInfoInterface|null
     */
    public function getMagentoDebugInfo(): ?MagentoDebugInfoInterface;
}
