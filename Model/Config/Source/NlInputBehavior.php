<?php

namespace Flekto\Postcode\Model\Config\Source;

class NlInputBehavior implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'zip_house', 'label' => __('Only zip code and house number')],
            ['value' => 'free', 'label' => __('Free address input')],
        ];
    }
}