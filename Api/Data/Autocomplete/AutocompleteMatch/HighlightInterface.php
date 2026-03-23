<?php

namespace PostcodeEu\AddressValidation\Api\Data\Autocomplete\AutocompleteMatch;

interface HighlightInterface
{
    /**
     * @return int[]
     */
    public function getOffsets(): array;
}
