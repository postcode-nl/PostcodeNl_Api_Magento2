<?php

namespace Flekto\Postcode\Api\Data;

interface AutocompleteInterface
{
    /**
     * @return Flekto\Postcode\Api\Data\Autocomplete\MatchInterface[]
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
     * @return Flekto\Postcode\Api\Data\MagentoDebugInfoInterface|null
     */
    public function getMagentoDebugInfo(): ?MagentoDebugInfoInterface;
}
