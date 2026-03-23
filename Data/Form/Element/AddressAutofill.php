<?php

namespace PostcodeEu\AddressValidation\Data\Form\Element;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\CollectionFactory;
use Magento\Framework\Data\Form\Element\Factory;
use Magento\Framework\Escaper;
use Magento\Framework\View\LayoutInterface;

/**
 * Add custom element for address autocomplete controls
 */
class AddressAutofill extends AbstractElement
{
    /** @var LayoutInterface */
    protected $_layout;

    /**
     * @param Factory $factoryElement
     * @param CollectionFactory $factoryCollection
     * @param Escaper $escaper
     * @param LayoutInterface $layout
     * @param array $data
     */
    public function __construct(
        Factory $factoryElement,
        CollectionFactory $factoryCollection,
        Escaper $escaper,
        LayoutInterface $layout,
        $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->setType('postcode-eu-address-autofill');
        $this->_layout = $layout;
    }

    /**
     * Get element HTML
     *
     * @return string
     */
    public function getElementHtml(): string
    {
        $block = $this->_layout->createBlock(
            \Magento\Backend\Block\Template::class,
            $this->getId(),
            [
                'data' => [
                    'template' => 'PostcodeEu_AddressValidation::form/element/address-autofill.phtml',
                    'jsLayout' => $this->getJsLayout(),
                ],
            ],
        );

        return $block->toHtml();
    }

    /**
     * Get JS layout
     *
     * @return array
     */
    public function getJsLayout(): array
    {
        return [
            'components' => [
                $this->getId() => [
                    'component' => 'PostcodeEu_AddressValidation/js/view/address/autofill',
                    'config' => [
                        'settings' => $this->getData('settings'),
                        'htmlIdPrefix' => $this->getData('htmlIdPrefix'),
                        'addressType' => $this->getData('addressType'),
                        'countryCode' => $this->getData('countryCode'),
                        'visible' => $this->getData('visible'),
                    ],
                    'children' => [
                        'address_autofill_nl' => [
                            'component' => 'PostcodeEu_AddressValidation/js/view/form/sales/order_create/address-autofill-nl',
                            'config' => [
                                'componentDisabled' => $this->getData('isNlComponentDisabled'),
                            ],
                            'children' => [
                                'postcode' => [
                                    'component' => 'Magento_Ui/js/form/element/abstract',
                                    'label' => __('Zip/Postal Code'),
                                    'config' => [
                                        'template' => 'ui/form/field',
                                        'elementTmpl' => 'PostcodeEu_AddressValidation/form/element/address-autofill-field',
                                        'placeholder' => '1234 AB',
                                        'imports' => [
                                            'visible' => '${ $.parentName }:visible',
                                        ],
                                    ],
                                ],
                                'house_number' => [
                                    'component' => 'Magento_Ui/js/form/element/abstract',
                                    'label' => __('House number and addition'),
                                    'additionalClasses' => [
                                        'address-autofill-nl-house-number' => true,
                                    ],
                                    'config' => [
                                        'template' => 'ui/form/field',
                                        'elementTmpl' => 'PostcodeEu_AddressValidation/form/element/address-autofill-field',
                                        'imports' => [
                                            'visible' => '${ $.parentName }:visible',
                                        ],
                                    ],
                                ],
                                'house_number_select' => [
                                    'component' => 'Magento_Ui/js/form/element/select',
                                    'label' => __('Which house number do you mean?'),
                                    'config' => [
                                        'caption' => __('- Select house number -'),
                                        'template' => 'ui/form/field',
                                        'visible' => false,
                                    ],
                                ],
                            ],
                        ],
                        'address_autofill_intl' => [
                            'component' => 'PostcodeEu_AddressValidation/js/view/form/sales/order_create/address-autofill-intl',
                            'label' => __('Find an address'),
                            'placeholder' => __('City, street or postcode')
                        ],
                        'address_autofill_error' => [
                            'component' => 'Magento_Ui/js/form/components/html',
                            'config' => [
                                'visible' => false,
                                'listens' => [
                                    '${$.parentName}:error' => 'content',
                                    'content' => 'visible',
                                ],
                                'additionalClasses' => [
                                    'admin__field-note' => true,
                                    'address-autofill-warning' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
