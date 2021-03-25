<?php

namespace Flekto\Postcode\Api\Data\Autocomplete\Match;

interface HighlightInterface
{
	/**
     * @return int[]
	 */
    public function getOffsets(): array;
}
