<?php

namespace Flekto\Postcode\Api\Data\Autocomplete;

interface MatchInterface
{
    /**
     * @return string
     */
    public function getValue(): string;

    /**
     * @return string
     */
    public function getLabel(): string;

    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * @return string
     */
    public function getPrecision(): string;

    /**
     * @return string
     */
    public function getContext(): string;

    /**
     * @return Flekto\Postcode\Api\Data\Autocomplete\Match\HighlightInterface[]
     */
    public function getHighlights(): array;
}
