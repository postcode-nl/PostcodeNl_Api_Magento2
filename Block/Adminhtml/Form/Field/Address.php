<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Flekto\Postcode\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Html\Select;

class Address extends Select
{
    private $scopeConfig;
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        array $data = []
    ) {
        $this->scopeConfig = $context->getScopeConfig();
        parent::__construct($context,$data);
    }
    /**
     * Set "name" for <select> element
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Set "id" for <select> element
     *
     * @param $value
     * @return $this
     */
    public function setInputId($value)
    {
        return $this->setId($value);
    }


    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }
        return parent::_toHtml();
    }

    private function getSourceOptions(): array
    {
        $extrafields = false;
        if($this->scopeConfig->getValue('postcodenl_api/general/address_fields_extra')) $extrafields = explode("\n", $this->scopeConfig->getValue('postcodenl_api/general/address_fields_extra'));
        $defaultFields = [
            'firstname',
            'lastname',
            'postcode_nl',
            'street',
            'postcode',
            'city',
            'country_id',
            'region_id',
            'telephone',
            'company',
            'vat_id'
        ];
        $ar = [];
        foreach($defaultFields as $field){
            $ar[] = ['label' => str_replace('_', ' ', str_replace('_id', '', ucfirst($field))), 'value' => $field];
        }
        if($extrafields){
            foreach($extrafields as $field){
                $ar[] = ['label' => str_replace('_', ' ', str_replace('_id', '', ucfirst($field))), 'value' => $field];
            }
        }
        return $ar;
    }
}

