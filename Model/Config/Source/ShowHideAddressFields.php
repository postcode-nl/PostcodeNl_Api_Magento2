<?php

namespace Flekto\Postcode\Model\Config\Source;

class ShowHideAddressFields implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'show', 'label' => __('Show fields (default)')],
            ['value' => 'disable', 'label' => __('Disable fields before autocomplete finished')],
            ['value' => 'hide', 'label' => __('Hide fields before autocomplete finished')],
        ];
    }
}