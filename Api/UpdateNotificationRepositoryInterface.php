<?php

namespace PostcodeEu\AddressValidation\Api;

use PostcodeEu\AddressValidation\Api\Data\UpdateNotificationInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Update notification CRUD interface.
 * @api
 */
interface UpdateNotificationRepositoryInterface
{
    /**
     * @param UpdateNotificationInterface $notification
     * @return UpdateNotificationInterface
     * @throws LocalizedException
     */
    public function save(UpdateNotificationInterface $notification): UpdateNotificationInterface;

    /**
     * @param string $version
     * @return UpdateNotificationInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getByVersion(string $version): UpdateNotificationInterface;

    /**
     * @param string $version
     * @throws LocalizedException
     */
    public function setVersionNotified(string $version): void;

    /**
     * @param string $version
     * @return bool
     * @throws LocalizedException
     */
    public function isVersionNotified(string $version): bool;
}
