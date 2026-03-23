<?php

namespace PostcodeEu\AddressValidation\Model\Config\Source;

use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;

class DisabledCountries implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var StoreConfigHelper
     */
    private $_storeConfigHelper;

    /**
     * Constructor
     *
     * @param StoreConfigHelper $storeConfigHelper
     */
    public function __construct(StoreConfigHelper $storeConfigHelper)
    {
        $this->_storeConfigHelper = $storeConfigHelper;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];

        foreach ($this->_storeConfigHelper->getSupportedCountryNames() as $iso2 => $name) {
            $options[] = ['value' => $iso2, 'label' => $name];
        }

        return $options;
    }
}
