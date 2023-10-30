<?php

namespace Flekto\Postcode\Model\Config\Source;

use Flekto\Postcode\Helper\StoreConfigHelper;

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

        foreach ($this->_storeConfigHelper->getSupportedCountries() as $country) {
            $options[] = [
                'value' => $country->iso2,
                'label' => $country->name,
            ];
        }

        return $options;
    }
}
