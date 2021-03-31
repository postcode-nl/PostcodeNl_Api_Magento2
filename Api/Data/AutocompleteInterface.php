<?php

namespace Flekto\Postcode\Api\Data;

interface AutocompleteInterface
{
    /**
     * @return Flekto\Postcode\Api\Data\Autocomplete\MatchInterface[]
     */
    public function getMatches(): array;
}
