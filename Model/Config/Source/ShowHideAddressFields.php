<?php

namespace PostcodeEu\AddressValidation\Model\Config\Source;

class ShowHideAddressFields implements \Magento\Framework\Data\OptionSourceInterface
{
    public const SHOW = 'show';
    public const DISABLE = 'disable';
    public const HIDE = 'hide';
    public const FORMAT = 'format';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => static::FORMAT, 'label' => __('Hide fields and show a formatted address instead (default)')],
            ['value' => static::DISABLE, 'label' => __('Disable fields before autocomplete finished')],
            ['value' => static::HIDE, 'label' => __('Hide fields before autocomplete finished')],
            ['value' => static::SHOW, 'label' => __('Show fields')],
        ];
    }
}
