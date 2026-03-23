<?php

namespace PostcodeEu\AddressValidation\Model\UpdateNotification;

use PostcodeEu\AddressValidation\Api\UpdateNotificationRepositoryInterface;
use Magento\Framework\Notification\NotifierInterface;

class UpdateNotifier
{
    /**
     * @var NotifierInterface
     */
    protected $_notifier;

    /**
     * @var UpdateNotificationRepositoryInterface
     */
    protected $_updateNotification;

    /**
     * Constructor
     *
     * @param NotifierInterface $notifier
     * @param UpdateNotificationRepositoryInterface $updateNotification
     */
    public function __construct(
        NotifierInterface $notifier,
        UpdateNotificationRepositoryInterface $updateNotification
    ) {
        $this->_notifier = $notifier;
        $this->_updateNotification = $updateNotification;
    }

    /**
     * Notifies about a new version.
     *
     * @param string $version
     * @return bool - True if notified about a new version, false otherwise.
     */
    public function notifyVersion(string $version): bool
    {
        if ($this->_updateNotification->isVersionNotified($version)) {
            return false;
        }

        $this->_notifier->addNotice(
            __('Postcode.eu Address Validation update available'),
            __('Stay ahead with our latest update.
                Get the newest features and improvements for our Postcode.eu address validation module.'),
            \PostcodeEu\AddressValidation\Helper\Data::MODULE_RELEASE_URL
        );
        $this->_updateNotification->setVersionNotified($version);
        return $this->_updateNotification->isVersionNotified($version);
    }
}
