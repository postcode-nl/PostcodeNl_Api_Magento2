<?php

namespace Flekto\Postcode\Model\Config\Source;

class NlInputBehavior implements \Magento\Framework\Option\ArrayInterface
{
    public const ZIP_HOUSE = 'zip_house';
    public const FREE = 'free';

    public function toOptionArray()
    {
        return [
            ['value' => 'zip_house', 'label' => __('Only zip code and house number (default)')],
            ['value' => 'free', 'label' => __('Free address input')],
        ];
    }
}
