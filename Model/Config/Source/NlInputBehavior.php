<?php

namespace PostcodeEu\AddressValidation\Model\Config\Source;

class NlInputBehavior implements \Magento\Framework\Data\OptionSourceInterface
{
    public const ZIP_HOUSE = 'zip_house';
    public const FREE = 'free';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => static::ZIP_HOUSE, 'label' => __('Only zip code and house number (default)')],
            ['value' => static::FREE, 'label' => __('Free address input')],
        ];
    }
}
