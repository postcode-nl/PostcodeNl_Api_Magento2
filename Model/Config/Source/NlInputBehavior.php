<?php

namespace Flekto\Postcode\Model\Config\Source;

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
            ['value' => 'zip_house', 'label' => __('Only zip code and house number (default)')],
            ['value' => 'free', 'label' => __('Free address input')],
        ];
    }
}
