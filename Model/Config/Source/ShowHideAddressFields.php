<?php

namespace Flekto\Postcode\Model\Config\Source;

class ShowHideAddressFields implements \Magento\Framework\Option\ArrayInterface
{
    public const SHOW = 'show';
    public const DISABLE = 'disable';
    public const HIDE = 'hide';
    public const FORMAT = 'format';

    public function toOptionArray()
    {
        return [
            ['value' => static::FORMAT, 'label' => __('Hide fields and show a formatted address instead (default)')],
            ['value' => static::DISABLE, 'label' => __('Disable fields before autocomplete finished')],
            ['value' => static::HIDE, 'label' => __('Hide fields before autocomplete finished')],
            ['value' => static::SHOW, 'label' => __('Show fields')],
        ];
    }
}
