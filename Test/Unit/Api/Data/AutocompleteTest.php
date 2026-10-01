<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Api\Data;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Api\Data\Autocomplete;
use PostcodeEu\AddressValidation\Api\Data\Autocomplete\AutocompleteMatch;
use PostcodeEu\AddressValidation\Api\Data\MagentoDebugInfo;

/**
 * Response array shaping for Autocomplete.
 */
class AutocompleteTest extends TestCase
{
    #[Test]
    public function full_response_shapes_matches_metadata_and_debug_info(): void
    {
        $response = [
            'matches' => [
                [
                    'value' => '9320 Aalst, Vlaamsegaaistraat ',
                    'label' => 'Vlaamsegaaistraat',
                    'description' => '9320 Aalst',
                    'precision' => 'Street',
                    'context' => 'bel3erZ25b6KdtUzzxMVdclyv5VIBsLUFPjeAvLk0j9x1XG6ACtQiVSF7O6iKUNWstqjMCHFtKFnqy3UUG4oZQ58Koaebzsy10eeZf0CTnA9yBTwkirbkLWznS1cHJNkwwFDm',
                    'highlights' => [[0, 11]],
                ],
                [
                    'value' => '3080 Tervuren, Vlaamsegaaienlaan ',
                    'label' => 'Vlaamsegaaienlaan',
                    'description' => '3080 Tervuren',
                    'precision' => 'Street',
                    'context' => 'bel3erZ25b6FxlO2NYYDUVFSy9ljtSGAlR7pgybRTpV9kqYIi2vEOUoEpHvvZWQ4PL8PKwwTLANMdlu7A2uLDtPPEeNcwvsmlSJbpL45PnKxd4fvKgxd73LikUU5Y6KTDoZh9',
                    'highlights' => [],
                ],
            ],
            'error' => 'some_error',
            'message' => 'some message',
            'exception' => 'some exception',
            'magento_debug_info' => ['moduleVersion' => '1.2.3'],
        ];

        $autocomplete = new Autocomplete($response);

        $matches = $autocomplete->getMatches();
        $this->assertCount(2, $matches);
        $this->assertInstanceOf(AutocompleteMatch::class, $matches[0]);
        $this->assertInstanceOf(AutocompleteMatch::class, $matches[1]);
        $this->assertSame('Vlaamsegaaistraat', $matches[0]->getLabel());
        $this->assertSame('Vlaamsegaaienlaan', $matches[1]->getLabel());
        $this->assertSame('some_error', $autocomplete->getError());
        $this->assertSame('some message', $autocomplete->getMessage());
        $this->assertSame('some exception', $autocomplete->getException());
        $this->assertInstanceOf(MagentoDebugInfo::class, $autocomplete->getMagentoDebugInfo());
    }

    #[Test]
    public function empty_response_yields_no_matches_nulls_and_no_debug_info(): void
    {
        $autocomplete = new Autocomplete([]);

        $this->assertSame([], $autocomplete->getMatches());
        $this->assertNull($autocomplete->getError());
        $this->assertNull($autocomplete->getMessage());
        $this->assertNull($autocomplete->getException());
        $this->assertNull($autocomplete->getMagentoDebugInfo());
    }

    #[Test]
    public function matches_without_debug_info_leave_debug_info_null(): void
    {
        $autocomplete = new Autocomplete([
            'matches' => [
                [
                    'value' => '9320 Aalst, Vlaamsegaaistraat ',
                    'label' => 'Vlaamsegaaistraat',
                    'description' => '9320 Aalst',
                    'precision' => 'Street',
                    'context' => 'bel3erZ25b6KdtUzzxMVdclyv5VIBsLUFPjeAvLk0j9x1XG6ACtQiVSF7O6iKUNWstqjMCHFtKFnqy3UUG4oZQ58Koaebzsy10eeZf0CTnA9yBTwkirbkLWznS1cHJNkwwFDm',
                    'highlights' => [[0, 11]],
                ],
            ],
        ]);

        $this->assertCount(1, $autocomplete->getMatches());
        $this->assertNull($autocomplete->getMagentoDebugInfo());
    }
}
