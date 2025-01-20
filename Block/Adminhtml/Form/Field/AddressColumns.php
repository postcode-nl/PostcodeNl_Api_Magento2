<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Flekto\Postcode\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;


class AddressColumns extends AbstractFieldArray
{
    private $addresFieldRenderer;
    private $positionRenderer;

    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    protected function _prepareToRender()
    {
        $this->addColumn('addressfield', ['label' => __('Address Field'), 'renderer' => $this->getAddresFieldRenderer()]);
        $this->addColumn('position', ['label' => __('Position'), 'renderer' => $this->getPositionRenderer(), 'class' => 'required-entry']);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];

        /*$product = $row->getProduct();
        if ($product !== null) {
            $options['option_' . $this->productRenderer()->calcOptionHash($product)] = 'selected="selected"';
        }*/

        $address = $row->getAddress();
        if ($address !== null) {
            $options['option_' . $this->getAddresFieldRenderer()->calcOptionHash($address)] = 'selected="selected"';
        }

        $position = $row->getPosition();
        if ($position !== null) {
            $options['option_' . $this->getPositionRenderer()->calcOptionHash($position)] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }


    private function getAddresFieldRenderer(){
        if (!$this->addresFieldRenderer) {
            $this->addresFieldRenderer = $this->getLayout()->createBlock(
                \Flekto\Postcode\Block\Adminhtml\Form\Field\Address::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->addresFieldRenderer;
    }

    private function getPositionRenderer(){
        if (!$this->positionRenderer) {
            $this->positionRenderer = $this->getLayout()->createBlock(
                \Flekto\Postcode\Block\Adminhtml\Form\Field\Position::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->positionRenderer;
    }
}

