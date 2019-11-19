<?php

namespace Flekto\Postcode\Block\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;

class ReadOnly extends \Magento\Config\Block\System\Config\Form\Field
{

    /**
     * _getElementHtml function.
     *
     * @access protected
     * @param AbstractElement $element
     * @return void
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $element->setData('readonly', 1);
        return $element->getElementHtml();
    }
}
