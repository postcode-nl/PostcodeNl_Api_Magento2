<?php

namespace Flekto\Postcode\Block\System\Config\Form\Apikey;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;


class Checker extends Field
{
    protected $_template = 'Flekto_Postcode::system/config/checker.phtml';


    /**
     * __construct function.
     *
     * @access public
     * @param Context $context
     * @param array $data (default: [])
     * @return void
     */
    public function __construct(Context $context,array $data = [])
    {
        parent::__construct($context, $data);
    }


    /**
     * render function.
     *
     * @access public
     * @param AbstractElement $element
     * @return void
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }


    /**
     * _getElementHtml function.
     *
     * @access protected
     * @param AbstractElement $element
     * @return void
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }


    /**
     * getAjaxUrl function.
     *
     * @access public
     * @return void
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('flekto_postcode/system_config/apicheck');
    }


    /**
     * getButtonHtml function.
     *
     * @access public
     * @return void
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'check_button',
                'label' => __('Validate & refresh data'),
            ]
        );

        return $button->toHtml();
    }
}
