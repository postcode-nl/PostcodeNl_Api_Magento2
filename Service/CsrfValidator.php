<?php

namespace PostcodeEu\AddressValidation\Service;

use Magento\Framework\App\Area;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\State;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;

class CsrfValidator
{
    /** @var FormKeyValidator */
    private FormKeyValidator $_formKeyValidator;
    /** @var HttpRequest */
    private HttpRequest $_request;
    /** @var State */
    private State $_appState;

    /**
     * @param FormKeyValidator $formKeyValidator
     * @param HttpRequest $request
     * @param State $appState
     */
    public function __construct(
        FormKeyValidator $formKeyValidator,
        HttpRequest $request,
        State $appState
    ) {
        $this->_formKeyValidator = $formKeyValidator;
        $this->_request = $request;
        $this->_appState = $appState;
    }

    /**
     * Validate the request.
     */
    public function validate(): void
    {
        try {
            if ($this->_appState->getAreaCode() === Area::AREA_ADMINHTML) {
                return;
            }
        } catch (LocalizedException $e) {
            // Area code not set.
        }

        if (!$this->_request->isAjax() || !$this->_formKeyValidator->validate($this->_request)) {
            throw new LocalizedException(__('Invalid request'));
        }
    }
}
