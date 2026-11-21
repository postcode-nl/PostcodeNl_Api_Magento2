<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Model\System\Message;

use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Notification\MessageInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Model\System\Message\RebrandNotice;

/**
 * Legacy-namespace detection and rebrand text for RebrandNotice.
 */
class RebrandNoticeTest extends TestCase
{
    #[Test]
    public function message_identity_is_stable(): void
    {
        $notice = $this->createNotice();

        $this->assertSame('postcode_eu_rebrand_notice', $notice->getIdentity());
    }

    #[Test]
    public function severity_is_minor(): void
    {
        $notice = $this->createNotice();

        $this->assertSame(MessageInterface::SEVERITY_MINOR, $notice->getSeverity());
    }

    #[Test]
    #[DataProvider('moduleListProvider')]
    public function notice_shown_when_legacy_namespace_sequence_present(array $modules, bool $expected): void
    {
        $notice = $this->createNotice($modules);

        $this->assertSame($expected, $notice->isDisplayed());
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function moduleListProvider(): array
    {
        return [
            'legacy module sequence' => [
                ['PostcodeEu_AddressValidation' => ['sequence' => ['Flekto_Postcode']]],
                true,
            ],
            'legacy module with other sequence entries' => [
                ['PostcodeEu_AddressValidation' => ['sequence' => ['Magento_Checkout', 'Flekto_Postcode']]],
                true,
            ],
            'legacy module later in list' => [
                [
                    'Magento_Checkout' => ['sequence' => ['Magento_Sales']],
                    'PostcodeEu_AddressValidation' => ['sequence' => ['Flekto_Postcode']],
                ],
                true,
            ],
            'empty module list' => [[], false],
            'sequence without legacy module' => [
                ['PostcodeEu_AddressValidation' => ['sequence' => ['Magento_Checkout']]],
                false,
            ],
            'module without sequence' => [
                ['PostcodeEu_AddressValidation' => ['name' => 'Postcode.eu Address Validation']],
                false,
            ],
        ];
    }

    #[Test]
    public function text_names_old_and_new_namespaces(): void
    {
        $notice = $this->createNotice();

        $text = $notice->getText();

        $this->assertStringContainsString('rebranded', $text);
        $this->assertStringContainsString('Flekto\Postcode', $text);
        $this->assertStringContainsString('PostcodeEu\AddressValidation', $text);
    }

    /**
     * @param array<string, mixed> $modules
     */
    private function createNotice(array $modules = []): RebrandNotice
    {
        $moduleList = $this->createStub(ModuleListInterface::class);
        $moduleList->method('getAll')->willReturn($modules);

        return new RebrandNotice($moduleList);
    }
}
