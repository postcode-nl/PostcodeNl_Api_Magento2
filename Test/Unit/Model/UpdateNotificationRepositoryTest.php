<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Model\ResourceModel\UpdateNotification as UpdateNotificationResource;
use PostcodeEu\AddressValidation\Model\UpdateNotification;
use PostcodeEu\AddressValidation\Model\UpdateNotificationFactory;
use PostcodeEu\AddressValidation\Model\UpdateNotificationRepository;

/**
 * Lookup, save and notified-state behaviour for UpdateNotificationRepository.
 */
class UpdateNotificationRepositoryTest extends TestCase
{
    #[Test]
    public function missing_version_never_notified(): void
    {
        $notification = $this->createStub(UpdateNotification::class);
        $notification->method('getId')->willReturn(null);

        $factory = $this->createStub(UpdateNotificationFactory::class);
        $factory->method('create')->willReturn($notification);

        $repository = new UpdateNotificationRepository(
            $this->createStub(UpdateNotificationResource::class),
            $factory
        );

        $this->assertFalse($repository->isVersionNotified('1.3.0'));
    }

    #[Test]
    public function stored_notified_version_reports_notified(): void
    {
        $notification = $this->createStub(UpdateNotification::class);
        $notification->method('getId')->willReturn(7);
        $notification->method('getNotified')->willReturn(true);

        $factory = $this->createStub(UpdateNotificationFactory::class);
        $factory->method('create')->willReturn($notification);

        $repository = new UpdateNotificationRepository(
            $this->createStub(UpdateNotificationResource::class),
            $factory
        );

        $this->assertTrue($repository->isVersionNotified('1.3.0'));
    }

    #[Test]
    public function missing_version_creates_and_saves_new_record(): void
    {
        $missing = $this->createNotification();
        $created = $this->createNotification();

        $factory = $this->createStub(UpdateNotificationFactory::class);
        $factory->method('create')->willReturnOnConsecutiveCalls($missing, $created);

        $saved = null;
        $resource = $this->createMock(UpdateNotificationResource::class);
        $resource->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (UpdateNotification $model) use (&$saved): void {
                $saved = $model;
            });

        $repository = new UpdateNotificationRepository($resource, $factory);

        $repository->setVersionNotified('1.3.0');

        $this->assertSame($created, $saved);
        $this->assertSame('1.3.0', $saved->getVersion());
        $this->assertTrue($saved->getNotified());
    }

    #[Test]
    public function existing_version_updated_and_saved(): void
    {
        $notification = $this->createNotification(7);

        $factory = $this->createStub(UpdateNotificationFactory::class);
        $factory->method('create')->willReturn($notification);

        $saved = null;
        $resource = $this->createMock(UpdateNotificationResource::class);
        $resource->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (UpdateNotification $model) use (&$saved): void {
                $saved = $model;
            });

        $repository = new UpdateNotificationRepository($resource, $factory);

        $repository->setVersionNotified('1.3.0');

        $this->assertSame($notification, $saved);
        $this->assertSame('1.3.0', $saved->getVersion());
        $this->assertTrue($saved->getNotified());
    }

    #[Test]
    public function unknown_version_lookup_raises_no_such_entity(): void
    {
        $notification = $this->createStub(UpdateNotification::class);
        $notification->method('getId')->willReturn(null);

        $factory = $this->createStub(UpdateNotificationFactory::class);
        $factory->method('create')->willReturn($notification);

        $repository = new UpdateNotificationRepository(
            $this->createStub(UpdateNotificationResource::class),
            $factory
        );

        $this->expectException(NoSuchEntityException::class);

        $repository->getByVersion('1.3.0');
    }

    #[Test]
    public function known_version_lookup_returns_record(): void
    {
        $notification = $this->createStub(UpdateNotification::class);
        $notification->method('getId')->willReturn(7);

        $factory = $this->createStub(UpdateNotificationFactory::class);
        $factory->method('create')->willReturn($notification);

        $repository = new UpdateNotificationRepository(
            $this->createStub(UpdateNotificationResource::class),
            $factory
        );

        $this->assertSame($notification, $repository->getByVersion('1.3.0'));
    }

    #[Test]
    public function save_persists_record_and_returns_it(): void
    {
        $notification = $this->createStub(UpdateNotification::class);

        $resource = $this->createMock(UpdateNotificationResource::class);
        $resource->expects($this->once())->method('save')->with($notification);

        $repository = new UpdateNotificationRepository(
            $resource,
            $this->createStub(UpdateNotificationFactory::class)
        );

        $this->assertSame($notification, $repository->save($notification));
    }

    #[Test]
    public function stored_version_with_notified_false_reports_not_notified(): void
    {
        $notification = $this->createStub(UpdateNotification::class);
        $notification->method('getId')->willReturn(7);
        $notification->method('getNotified')->willReturn(false);

        $factory = $this->createStub(UpdateNotificationFactory::class);
        $factory->method('create')->willReturn($notification);

        $repository = new UpdateNotificationRepository(
            $this->createStub(UpdateNotificationResource::class),
            $factory
        );

        $this->assertFalse($repository->isVersionNotified('1.3.0'));
    }

    #[Test]
    public function version_lookup_loads_by_version_field(): void
    {
        $notification = $this->createStub(UpdateNotification::class);
        $notification->method('getId')->willReturn(7);

        $factory = $this->createStub(UpdateNotificationFactory::class);
        $factory->method('create')->willReturn($notification);

        $resource = $this->createMock(UpdateNotificationResource::class);
        $resource->expects($this->once())->method('load')->with($notification, '1.3.0', 'version');

        $repository = new UpdateNotificationRepository($resource, $factory);

        $this->assertSame($notification, $repository->getByVersion('1.3.0'));
    }

    #[Test]
    public function set_version_notified_wraps_resource_failure(): void
    {
        $missing = $this->createNotification();
        $created = $this->createNotification();

        $factory = $this->createStub(UpdateNotificationFactory::class);
        $factory->method('create')->willReturnOnConsecutiveCalls($missing, $created);

        $resource = $this->createStub(UpdateNotificationResource::class);
        $resource->method('save')->willThrowException(new \RuntimeException('deadlock'));

        $repository = new UpdateNotificationRepository($resource, $factory);

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('deadlock');

        $repository->setVersionNotified('1.3.0');
    }

    #[Test]
    public function resource_save_failure_raises_could_not_save(): void
    {
        $notification = $this->createStub(UpdateNotification::class);

        $resource = $this->createStub(UpdateNotificationResource::class);
        $resource->method('save')->willThrowException(new \Exception('deadlock'));

        $repository = new UpdateNotificationRepository(
            $resource,
            $this->createStub(UpdateNotificationFactory::class)
        );

        $this->expectException(CouldNotSaveException::class);

        $repository->save($notification);
    }

    private function createNotification(?int $id = null): UpdateNotification
    {
        $resource = $this->createStub(UpdateNotificationResource::class);
        $resource->method('getIdFieldName')->willReturn('id');

        $notification = new UpdateNotification(
            $this->createStub(Context::class),
            $this->createStub(Registry::class),
            $resource
        );

        if ($id !== null) {
            $notification->setId($id);
        }

        return $notification;
    }
}
