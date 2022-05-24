<?php

namespace Flekto\Postcode\Api\Data\Autocomplete\AutocompleteMatch;

interface HighlightInterface
{
	/**
     * @return int[]
	 */
    public function getOffsets(): array;
}
