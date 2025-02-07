<?php

namespace Flekto\Postcode\Block\Checkout;

use Magento\Checkout\Block\Checkout\AttributeMerger as BaseAttributeMerger;

/**
 * Fields attribute merger extension.
 */
class AttributeMerger extends BaseAttributeMerger
{
    /**
     * Retrieve field configuration for street address attribute
     *
     * @param string $attributeCode
     * @param array $attributeConfig
     * @param string $providerName name of the storage container used by UI component
     * @param string $dataScopePrefix
     * @return array
     */
    protected function getMultilineFieldConfig($attributeCode, array $attributeConfig, $providerName, $dataScopePrefix)
    {
        $config = parent::getMultilineFieldConfig($attributeCode, $attributeConfig, $providerName, $dataScopePrefix);

        if ($attributeCode === 'street') {
            $config['component'] = 'Flekto_Postcode/js/form/components/street';
            $config['config']['template'] = 'Flekto_Postcode/group/street';
        }

        return $config;
    }
}
