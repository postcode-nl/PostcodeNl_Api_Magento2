<?php

namespace PostcodeEu\AddressValidation\Plugin;

use PostcodeEu\AddressValidation\Helper\Data as DataHelper;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Model\Config\Source\AdminAddressAutocompleteBehavior;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Data\Form;
use Magento\Sales\Block\Adminhtml\Order\Create\Form\Address as AddressBlock;

class AddAddressAutofillToOrderCreateForm
{
    /**
     * @var StoreConfigHelper
     */
    private $_storeConfigHelper;

    /**
     * @var DirectoryHelper
     */
    private $_directoryHelper;

    /**
     * @var DataHelper
     */
    private $_dataHelper;

    /**
     * Constructor
     *
     * @param StoreConfigHelper $storeConfigHelper
     * @param DataHelper $dataHelper
     * @param DirectoryHelper $directoryHelper
     */
    public function __construct(
        StoreConfigHelper $storeConfigHelper,
        DataHelper $dataHelper,
        DirectoryHelper $directoryHelper
    ) {
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_dataHelper = $dataHelper;
        $this->_directoryHelper = $directoryHelper;
    }

    /**
     * Add address autofill field to form
     *
     * @param AddressBlock $subject
     * @param Form $form
     * @return Form
     */
    public function afterGetForm(AddressBlock $subject, Form $form)
    {
        $fieldset = $form->getElement('main');
        $autocompleteBehavior = $this->_storeConfigHelper->getValue('admin_address_autocomplete_behavior');

        if ($fieldset === null
            || $this->_dataHelper->isDisabled()
            || $autocompleteBehavior ===  AdminAddressAutocompleteBehavior::DISABLE
            || $subject instanceof \Magento\Sales\Block\Adminhtml\Order\Address\Form // Exclude edit form.
        ) {
            return $form;
        }

        $fieldset->addType(
            'postcode-eu-address-autofill',
            \PostcodeEu\AddressValidation\Data\Form\Element\AddressAutofill::class,
        );
        $addressType = $subject->getIsShipping() ? 'shipping' : 'billing';
        $fieldId = $addressType . '_address_autofill';
        $countryId = $subject->getAddress()->getCountryId() ?? $this->_directoryHelper->getDefaultCountry();
        $isVisible = in_array($countryId, $this->_storeConfigHelper->getEnabledCountries());

        if ($autocompleteBehavior !== AdminAddressAutocompleteBehavior::DEFAULT) {
            $isNlComponentDisabled = $autocompleteBehavior === AdminAddressAutocompleteBehavior::SINGLE_INPUT;
        }

        if ($form->getElement($fieldId) === null) {
            $fieldset->addField(
                $fieldId,
                'postcode-eu-address-autofill',
                [
                    'settings' => $this->_storeConfigHelper->getJsinit(),
                    'htmlIdPrefix' => $form->getHtmlIdPrefix(),
                    'addressType' => $addressType,
                    'label' => __('Address autocomplete'),
                    'countryCode' => $countryId,
                    'visible' => $isVisible,
                    'css_class' => $isVisible ? '' : 'hidden',
                    'isNlComponentDisabled' => $isNlComponentDisabled ?? $this->_dataHelper->isNlComponentDisabled(),
                ],
                'country_id',
            );
        }

        return $form;
    }
}
