<?php

namespace Flekto\Postcode\Model\Config\Source;

class ShowHideAddressFields implements \Magento\Framework\Option\ArrayInterface
{
    const SHOW = 'show';
    const DISABLE = 'disable';
    const HIDE = 'hide';
    const FORMAT = 'format';

    public function toOptionArray()
    {
        return [
            ['value' => static::SHOW, 'label' => __('Show fields (default)')],
            ['value' => static::DISABLE, 'label' => __('Disable fields before autocomplete finished')],
            ['value' => static::HIDE, 'label' => __('Hide fields before autocomplete finished')],
            ['value' => static::FORMAT, 'label' => __('Hide fields and show a formatted address instead')],
        ];
    }
}
