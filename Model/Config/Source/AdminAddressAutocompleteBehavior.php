<?php

namespace PostcodeEu\AddressValidation\Model\Config\Source;

class AdminAddressAutocompleteBehavior implements \Magento\Framework\Data\OptionSourceInterface
{
    public const DEFAULT = 'default';
    public const SINGLE_INPUT = 'single_input';
    public const DUTCH_LOOKUP = 'dutch_lookup';
    public const DISABLE = 'disable';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => static::DEFAULT, 'label' => __('Equal to configuration for checkout')],
            ['value' => static::SINGLE_INPUT, 'label' => __('Use free address input for Dutch address')],
            ['value' => static::DUTCH_LOOKUP, 'label' => __('Use zip code and house number inputs for Dutch address')],
            ['value' => static::DISABLE, 'label' => __('Disabled')],
        ];
    }
}
