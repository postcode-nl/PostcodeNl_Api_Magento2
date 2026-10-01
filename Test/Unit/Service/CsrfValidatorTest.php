<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Service;

use Magento\Framework\App\Area;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\State;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Service\CsrfValidator;

/**
 * CSRF validation gate for admin, ajax and frontend requests.
 */
class CsrfValidatorTest extends TestCase
{
    #[Test]
    public function adminhtml_area_bypasses_form_key_check(): void
    {
        $formKeyValidator = $this->createMock(FormKeyValidator::class);
        $formKeyValidator->expects($this->never())->method('validate');

        $appState = $this->createStub(State::class);
        $appState->method('getAreaCode')->willReturn(Area::AREA_ADMINHTML);

        $request = $this->createStub(HttpRequest::class);
        $request->method('isAjax')->willReturn(false);

        $this->createValidator($formKeyValidator, $request, $appState)->validate();
    }

    #[Test]
    public function area_not_set_falls_through_to_ajax_check_and_throws(): void
    {
        $appState = $this->createStub(State::class);
        $appState->method('getAreaCode')
            ->willThrowException(new LocalizedException(__('Area code is not set')));

        $request = $this->createStub(HttpRequest::class);
        $request->method('isAjax')->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid request');

        $this->createValidator($this->createStub(FormKeyValidator::class), $request, $appState)->validate();
    }

    #[Test]
    public function non_ajax_request_is_rejected_even_with_valid_form_key(): void
    {
        $formKeyValidator = $this->createStub(FormKeyValidator::class);
        $formKeyValidator->method('validate')->willReturn(true);

        $appState = $this->createStub(State::class);
        $appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);

        $request = $this->createStub(HttpRequest::class);
        $request->method('isAjax')->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid request');

        $this->createValidator($formKeyValidator, $request, $appState)->validate();
    }

    #[Test]
    public function ajax_request_with_invalid_form_key_throws(): void
    {
        $appState = $this->createStub(State::class);
        $appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);

        $request = $this->createStub(HttpRequest::class);
        $request->method('isAjax')->willReturn(true);

        $formKeyValidator = $this->createStub(FormKeyValidator::class);
        $formKeyValidator->method('validate')->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid request');

        $this->createValidator($formKeyValidator, $request, $appState)->validate();
    }

    #[Test]
    public function ajax_request_with_valid_form_key_passes(): void
    {
        $appState = $this->createStub(State::class);
        $appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);

        $request = $this->createStub(HttpRequest::class);
        $request->method('isAjax')->willReturn(true);

        $formKeyValidator = $this->createMock(FormKeyValidator::class);
        $formKeyValidator->expects($this->once())
            ->method('validate')
            ->with($request)
            ->willReturn(true);

        $this->createValidator($formKeyValidator, $request, $appState)->validate();
    }

    /**
     * @param FormKeyValidator $formKeyValidator
     * @param HttpRequest $request
     * @param State $appState
     * @return CsrfValidator
     */
    private function createValidator(
        FormKeyValidator $formKeyValidator,
        HttpRequest $request,
        State $appState
    ): CsrfValidator {
        return new CsrfValidator($formKeyValidator, $request, $appState);
    }
}
