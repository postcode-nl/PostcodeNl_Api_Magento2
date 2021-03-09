<?php

namespace Flekto\Postcode\Api\Data;

class Autocomplete implements AutocompleteInterface
{
    /**
     * @var Autocomplete\MatchInterface[]
     */
	public $matches = [];

    /**
     * __construct function.
     *
     * @access public
     * @param array $response
     * @return void
     */
	public function __construct(array $response)
	{
        foreach ($response['matches'] as $match)
        {
            $this->matches[] = new Autocomplete\Match($match);
        }
	}

    /**
     * @inheritdoc
     */
    public function getMatches(): array
    {
        return $this->matches;
    }
}
