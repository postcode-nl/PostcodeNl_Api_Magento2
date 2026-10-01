<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Model\UpdateNotification;

use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Api\UpdateNotificationRepositoryInterface;
use PostcodeEu\AddressValidation\Helper\Data;
use PostcodeEu\AddressValidation\Model\UpdateNotification\UpdateNotifier;

/**
 * Dedupe and admin notification contract for UpdateNotifier.
 */
class UpdateNotifierTest extends TestCase
{
    #[Test]
    public function already_notified_version_returns_false_without_notifying(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->never())->method('addNotice');

        $updateNotification = $this->createMock(UpdateNotificationRepositoryInterface::class);
        $updateNotification->expects($this->once())->method('isVersionNotified')->with('1.3.0')->willReturn(true);
        $updateNotification->expects($this->never())->method('setVersionNotified');

        $updateNotifier = new UpdateNotifier($notifier, $updateNotification);

        $this->assertFalse($updateNotifier->notifyVersion('1.3.0'));
    }

    #[Test]
    public function new_version_notifies_once_and_returns_true(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())
            ->method('addNotice')
            ->with(
                $this->callback(
                    fn (Phrase $phrase): bool => $phrase->getText() === 'Postcode.eu Address Validation update available'
                ),
                $this->callback(
                    fn (Phrase $phrase): bool => str_contains($phrase->getText(), 'Get the newest features and improvements')
                ),
                Data::MODULE_RELEASE_URL
            );

        $updateNotification = $this->createMock(UpdateNotificationRepositoryInterface::class);
        $updateNotification->expects($this->once())
            ->method('isVersionNotified')
            ->with('1.3.0')
            ->willReturn(false);
        $updateNotification->expects($this->once())
            ->method('setVersionNotified')
            ->with('1.3.0');

        $updateNotifier = new UpdateNotifier($notifier, $updateNotification);

        $this->assertTrue($updateNotifier->notifyVersion('1.3.0'));
    }
}
