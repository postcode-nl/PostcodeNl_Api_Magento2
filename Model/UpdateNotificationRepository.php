<?php

namespace PostcodeEu\AddressValidation\Model;

use PostcodeEu\AddressValidation\Api\UpdateNotificationRepositoryInterface;
use PostcodeEu\AddressValidation\Api\Data\UpdateNotificationInterface;
use PostcodeEu\AddressValidation\Model\ResourceModel\UpdateNotification as UpdateNotificationResource;
use PostcodeEu\AddressValidation\Model\UpdateNotificationFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;

class UpdateNotificationRepository implements UpdateNotificationRepositoryInterface
{
    protected $_resource;
    protected $_notificationFactory;

    public function __construct(
        UpdateNotificationResource $resource,
        UpdateNotificationFactory $notificationFactory
    ) {
        $this->_resource = $resource;
        $this->_notificationFactory = $notificationFactory;
    }

    public function getByVersion(string $version): UpdateNotificationInterface
    {
        $notification = $this->_notificationFactory->create();
        $this->_resource->load($notification, $version, 'version');

        if (!$notification->getId()) {
            throw new NoSuchEntityException(__('Version "%1" not found', $version));
        }

        return $notification;
    }

    public function save(UpdateNotificationInterface $notification): UpdateNotificationInterface
    {
        try {
            $this->_resource->save($notification);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }

        return $notification;
    }

    public function setVersionNotified(string $version): void
    {
        try {
            $notification = $this->getByVersion($version);
        } catch (NoSuchEntityException $e) {
            $notification = $this->_notificationFactory->create();
        }

        $notification->setVersion($version);
        $notification->setNotified(true);
        $this->_resource->save($notification);
    }

    public function isVersionNotified(string $version): bool
    {
        try {
            $notification = $this->getByVersion($version);
            return $notification->getNotified();
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }
}
