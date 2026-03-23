<?php

namespace PostcodeEu\AddressValidation\Block\Customer\Address;

class Autofill extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Framework\View\LayoutInterface
     */
    protected $_layout;

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = []
    ) {
        $this->_layout = $context->getLayout();
        parent::__construct($context, $data);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        return $this->hasAddressFields() ? parent::_toHtml() : '';
    }

    /**
     * Check whether address fields are shown in the current layout.
     *
     * @return bool
     */
    private function hasAddressFields(): bool
    {
        if (!empty($this->_layout->getBlock('customer_address_edit'))) {
            return  true;
        }

        $registerBlock = $this->_layout->getBlock('customer_form_register');
        if (!empty($registerBlock) && $registerBlock->getData('show_address_fields')) {
            return  true;
        }

        return false;
    }
}
