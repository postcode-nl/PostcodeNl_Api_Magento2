<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Service;

use Magento\Framework\App\ProductMetadataInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\HTTP\Client\Curl;
use PostcodeEu\AddressValidation\Service\ApiAvailabilityMonitor;
use PostcodeEu\AddressValidation\Service\Exception\AuthenticationException;
use PostcodeEu\AddressValidation\Service\Exception\BadRequestException;
use PostcodeEu\AddressValidation\Service\Exception\CurlException;
use PostcodeEu\AddressValidation\Service\Exception\ForbiddenException;
use PostcodeEu\AddressValidation\Service\Exception\InvalidJsonResponseException;
use PostcodeEu\AddressValidation\Service\Exception\NotFoundException;
use PostcodeEu\AddressValidation\Service\Exception\ServiceUnavailableException;
use PostcodeEu\AddressValidation\Service\Exception\TooManyRequestsException;
use PostcodeEu\AddressValidation\Service\Exception\UnexpectedException;
use PostcodeEu\AddressValidation\Service\PostcodeApiClient;

/**
 * Response handling and HTTP status to exception mapping for PostcodeApiClient.
 */
class PostcodeApiClientTest extends TestCase
{
    #[Test]
    public function successful_api_response_returns_decoded_json(): void
    {
        $client = $this->createClientWithResponse(200, '{"foo":"bar"}');

        $result = $client->accountInfo();

        $this->assertSame(['foo' => 'bar'], $result);
    }

    #[Test]
    public function successful_response_with_invalid_json_raises_exception(): void
    {
        $this->expectException(InvalidJsonResponseException::class);

        $this->createClientWithResponse(200, 'not-json')->accountInfo();
    }

    #[Test]
    #[DataProvider('nonArrayJsonProvider')]
    public function valid_json_that_is_not_an_array_raises_exception(string $body): void
    {
        $this->expectException(InvalidJsonResponseException::class);

        $this->createClientWithResponse(200, $body)->accountInfo();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonArrayJsonProvider(): array
    {
        return [
            'string' => ['"hello"'],
            'integer' => ['42'],
            'boolean' => ['true'],
            'null' => ['null'],
        ];
    }

    #[Test]
    public function successful_response_records_success(): void
    {
        $monitor = $this->createMock(ApiAvailabilityMonitor::class);
        $monitor->expects($this->once())->method('recordSuccess');
        $monitor->expects($this->never())->method('recordFailure');

        $result = $this->createClientWithResponse(200, '{"foo":"bar"}', $monitor)->accountInfo();

        $this->assertSame(['foo' => 'bar'], $result);
    }

    #[Test]
    public function curl_failure_raises_curl_exception(): void
    {
        $this->expectException(CurlException::class);

        $curl = $this->createStub(Curl::class);
        $curl->method('get')->willThrowException(new \RuntimeException('Connection failed'));

        $this->createClient($curl)->accountInfo();
    }

    #[Test]
    #[DataProvider('httpStatusExceptionProvider')]
    public function non_success_http_status_maps_to_typed_exception(
        int $statusCode,
        string $exceptionClass,
        bool $recordsFailure
    ): void {
        $monitor = $this->createMock(ApiAvailabilityMonitor::class);
        $monitor->expects($recordsFailure ? $this->once() : $this->never())->method('recordFailure');
        $monitor->expects($this->never())->method('recordSuccess');

        $this->expectException($exceptionClass);

        $this->createClientWithResponse($statusCode, '{}', $monitor)->accountInfo();
    }

    /**
     * @return array<string, array{int, class-string<\Throwable>, bool}>
     */
    public static function httpStatusExceptionProvider(): array
    {
        return [
            '400' => [400, BadRequestException::class, false],
            '401' => [401, AuthenticationException::class, false],
            '403' => [403, ForbiddenException::class, false],
            '404' => [404, NotFoundException::class, false],
            '429' => [429, TooManyRequestsException::class, false],
            '503' => [503, ServiceUnavailableException::class, true],
            '500' => [500, UnexpectedException::class, true],
        ];
    }

    /**
     * @param int $statusCode
     * @param string $body
     * @param ApiAvailabilityMonitor|null $monitor
     * @return PostcodeApiClient
     */
    private function createClientWithResponse(
        int $statusCode,
        string $body = '{}',
        ?ApiAvailabilityMonitor $monitor = null
    ): PostcodeApiClient {
        $curl = $this->createStub(Curl::class);
        $curl->method('getStatus')->willReturn($statusCode);
        $curl->method('getBody')->willReturn($body);

        return $this->createClient($curl, $monitor);
    }

    /**
     * @param Curl $curl
     * @param ApiAvailabilityMonitor|null $monitor
     * @return PostcodeApiClient
     */
    private function createClient(Curl $curl, ?ApiAvailabilityMonitor $monitor = null): PostcodeApiClient
    {
        $storeConfigHelper = $this->createStub(StoreConfigHelper::class);
        $storeConfigHelper->method('getCurrentStoreBaseUrl')->willReturn('https://example.com/');
        $storeConfigHelper->method('getCredentials')->willReturn(['key' => 'key', 'secret' => 'secret']);
        $storeConfigHelper->method('getModuleVersion')->willReturn('1.0.0');

        $productMetadata = $this->createStub(ProductMetadataInterface::class);
        $productMetadata->method('getName')->willReturn('Magento');
        $productMetadata->method('getEdition')->willReturn('Community');
        $productMetadata->method('getVersion')->willReturn('2.4.8');

        return new PostcodeApiClient(
            $curl,
            $productMetadata,
            $storeConfigHelper,
            $monitor ?? $this->createStub(ApiAvailabilityMonitor::class)
        );
    }
}
