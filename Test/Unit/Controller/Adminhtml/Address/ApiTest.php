<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Controller\Adminhtml\Address;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\ServiceOutputProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Api\Data\AutocompleteInterface;
use PostcodeEu\AddressValidation\Api\PostcodeModelInterface;
use PostcodeEu\AddressValidation\Controller\Adminhtml\Address\Api;

/**
 * Parameter validation and whitespace handling in the admin address API controller.
 */
class ApiTest extends TestCase
{
    /** @var RequestInterface */
    private $request;

    /** @var ServiceOutputProcessor */
    private $serviceOutputProcessor;

    #[Test]
    public function autocomplete_term_keeps_leading_and_trailing_whitespace(): void
    {
        $postcodeModel = $this->createMock(PostcodeModelInterface::class);
        $controller = $this->createController($postcodeModel);
        $this->stubRequestParams([
            'method' => 'autocomplete',
            'context' => ' nld ',
            'term' => '  Damrak  ',
        ]);
        $postcodeModel->expects($this->once())
            ->method('getAddressAutocomplete')
            ->with('nld', '  Damrak  ')
            ->willReturn($this->createStub(AutocompleteInterface::class));
        $this->serviceOutputProcessor->method('process')->willReturn(['matches' => []]);

        $controller->execute();
    }

    #[Test]
    public function whitespace_only_term_is_forwarded_not_rejected(): void
    {
        $postcodeModel = $this->createMock(PostcodeModelInterface::class);
        $controller = $this->createController($postcodeModel);
        $this->stubRequestParams([
            'method' => 'autocomplete',
            'context' => 'nld',
            'term' => '   ',
        ]);
        $postcodeModel->expects($this->once())
            ->method('getAddressAutocomplete')
            ->with('nld', '   ')
            ->willReturn($this->createStub(AutocompleteInterface::class));
        $this->serviceOutputProcessor->method('process')->willReturn(['matches' => []]);

        $controller->execute();
    }

    #[Test]
    public function postcode_and_house_number_are_trimmed(): void
    {
        $postcodeModel = $this->createMock(PostcodeModelInterface::class);
        $controller = $this->createController($postcodeModel);
        $this->stubRequestParams([
            'method' => 'postcode',
            'postcode' => ' 1234AB ',
            'house_number' => ' 12 ',
        ]);
        $postcodeModel->expects($this->once())
            ->method('getNlAddress')
            ->with('1234AB', '12')
            ->willReturn(['address' => []]);
        $this->serviceOutputProcessor->method('process')->willReturn(['address' => []]);

        $controller->execute();
    }

    #[Test]
    public function whitespace_only_postcode_is_rejected_as_missing(): void
    {
        $resultJson = $this->createMock(Json::class);
        $controller = $this->createController(null, $resultJson);
        $this->stubRequestParams([
            'method' => 'postcode',
            'postcode' => '   ',
            'house_number' => '1',
        ]);
        $resultJson->expects($this->once())->method('setHttpResponseCode')->with(400)->willReturnSelf();
        $resultJson->expects($this->once())
            ->method('setData')
            ->with(['error' => 'Missing parameter `postcode`'])
            ->willReturnSelf();

        $controller->execute();
    }

    #[Test]
    public function unknown_method_is_rejected_as_bad_request(): void
    {
        $resultJson = $this->createMock(Json::class);
        $controller = $this->createController(null, $resultJson);
        $this->stubRequestParams(['method' => 'does_not_exist']);
        $resultJson->expects($this->once())->method('setHttpResponseCode')->with(400)->willReturnSelf();
        $resultJson->expects($this->once())
            ->method('setData')
            ->with(['error' => 'Invalid service method'])
            ->willReturnSelf();

        $controller->execute();
    }

    #[Test]
    public function non_string_method_is_rejected_as_bad_request(): void
    {
        $resultJson = $this->createMock(Json::class);
        $controller = $this->createController(null, $resultJson);
        $this->stubRequestParams(['method' => ['postcode']]);
        $resultJson->expects($this->once())->method('setHttpResponseCode')->with(400)->willReturnSelf();
        $resultJson->expects($this->once())
            ->method('setData')
            ->with(['error' => 'Invalid service method'])
            ->willReturnSelf();

        $controller->execute();
    }

    /**
     * @param PostcodeModelInterface|null $postcodeModel
     * @param Json|null $resultJson
     * @return Api
     */
    private function createController(
        ?PostcodeModelInterface $postcodeModel = null,
        ?Json $resultJson = null
    ): Api {
        $this->request = $this->createStub(RequestInterface::class);

        if ($resultJson === null) {
            $resultJson = $this->createStub(Json::class);
            $resultJson->method('setData')->willReturnSelf();
            $resultJson->method('setHttpResponseCode')->willReturnSelf();
        }

        $resultJsonFactory = $this->createStub(JsonFactory::class);
        $resultJsonFactory->method('create')->willReturn($resultJson);

        $this->serviceOutputProcessor = $this->createStub(ServiceOutputProcessor::class);

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($this->request);

        return new Api(
            $context,
            $resultJsonFactory,
            $postcodeModel ?? $this->createStub(PostcodeModelInterface::class),
            $this->serviceOutputProcessor
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function stubRequestParams(array $params): void
    {
        $this->request->method('getParam')->willReturnCallback(
            function ($key, $default = null) use ($params) {
                return $params[$key] ?? $default;
            }
        );
    }
}
