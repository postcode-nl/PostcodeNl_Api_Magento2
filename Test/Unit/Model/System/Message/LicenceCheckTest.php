<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Model\System\Message;

use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Helper\ApiClientHelper;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Model\System\Message\LicenceCheck;

/**
 * Licence warning visibility and text for LicenceCheck.
 */
class LicenceCheckTest extends TestCase
{
    #[Test]
    public function message_identity_is_stable(): void
    {
        $message = $this->createMessage();

        $this->assertSame('postcode_eu_licence_check', $message->getIdentity());
    }

    #[Test]
    public function severity_is_major(): void
    {
        $message = $this->createMessage();

        $this->assertSame(MessageInterface::SEVERITY_MAJOR, $message->getSeverity());
    }

    #[Test]
    #[DataProvider('accountStatusProvider')]
    public function licence_warning_visibility_follows_account_status(?string $status, bool $expected): void
    {
        $message = $this->createMessage(['account_status' => $status]);

        $this->assertSame($expected, $message->isDisplayed());
    }

    /**
     * @return array<string, array{string|null, bool}>
     */
    public static function accountStatusProvider(): array
    {
        return [
            'new' => [ApiClientHelper::API_ACCOUNT_STATUS_NEW, true],
            'invalid credentials' => [ApiClientHelper::API_ACCOUNT_STATUS_INVALID_CREDENTIALS, true],
            'inactive' => [ApiClientHelper::API_ACCOUNT_STATUS_INACTIVE, true],
            'missing' => [null, true],
            'active' => [ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE, false],
        ];
    }

    #[Test]
    public function text_includes_reason_link_and_action(): void
    {
        $url = 'https://admin.example.com/adminhtml/system_config/edit/section/postcodenl_api/';
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('adminhtml/system_config/edit', ['section' => 'postcodenl_api'])
            ->willReturn($url);

        $message = new LicenceCheck($this->createStub(StoreConfigHelper::class), $urlBuilder);

        $text = $message->getText();

        $this->assertStringContainsString('Your Postcode.eu API licence is invalid.', $text);
        $this->assertStringContainsString($url, $text);
        $this->assertStringContainsString('Check your API credentials.', $text);
    }

    /**
     * @param array{account_status?: string|null} $config
     */
    private function createMessage(array $config = []): LicenceCheck
    {
        $storeConfigHelper = $this->createStub(StoreConfigHelper::class);
        $storeConfigHelper->method('getValue')->willReturnCallback(
            fn (string $path) => $path === 'account_status' ? ($config['account_status'] ?? null) : null
        );

        return new LicenceCheck($storeConfigHelper, $this->createStub(UrlInterface::class));
    }
}
