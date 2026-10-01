<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Api\Data\Autocomplete;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Api\Data\Autocomplete\AutocompleteMatch;

/**
 * Match array mapping for AutocompleteMatch.
 */
class AutocompleteMatchTest extends TestCase
{
    #[Test]
    public function complete_match_array_populates_every_getter(): void
    {
        $match = [
            'value' => '9320 Aalst, Vlaamsegaaistraat ',
            'label' => 'Vlaamsegaaistraat',
            'description' => '9320 Aalst',
            'precision' => 'Street',
            'context' => 'bel3erZ25b6KdtUzzxMVdclyv5VIBsLUFPjeAvLk0j9x1XG6ACtQiVSF7O6iKUNWstqjMCHFtKFnqy3UUG4oZQ58Koaebzsy10eeZf0CTnA9yBTwkirbkLWznS1cHJNkwwFDm',
            'highlights' => [[0, 11], [13, 20]],
        ];

        $autocompleteMatch = new AutocompleteMatch($match);

        $this->assertSame('9320 Aalst, Vlaamsegaaistraat ', $autocompleteMatch->getValue());
        $this->assertSame('Vlaamsegaaistraat', $autocompleteMatch->getLabel());
        $this->assertSame('9320 Aalst', $autocompleteMatch->getDescription());
        $this->assertSame('Street', $autocompleteMatch->getPrecision());
        $this->assertSame(
            'bel3erZ25b6KdtUzzxMVdclyv5VIBsLUFPjeAvLk0j9x1XG6ACtQiVSF7O6iKUNWstqjMCHFtKFnqy3UUG4oZQ58Koaebzsy10eeZf0CTnA9yBTwkirbkLWznS1cHJNkwwFDm',
            $autocompleteMatch->getContext()
        );
        $this->assertSame([[0, 11], [13, 20]], $autocompleteMatch->getHighlights());
    }

    #[Test]
    public function incomplete_match_array_falls_back_to_defaults(): void
    {
        $autocompleteMatch = new AutocompleteMatch(['value' => 'V', 'label' => 'L']);

        $this->assertSame('V', $autocompleteMatch->getValue());
        $this->assertSame('L', $autocompleteMatch->getLabel());
        $this->assertSame('', $autocompleteMatch->getDescription());
        $this->assertSame('', $autocompleteMatch->getPrecision());
        $this->assertSame('', $autocompleteMatch->getContext());
        $this->assertSame([], $autocompleteMatch->getHighlights());
    }
}
