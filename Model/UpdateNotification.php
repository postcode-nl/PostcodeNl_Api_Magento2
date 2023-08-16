<?php

namespace Flekto\Postcode\Model;

use Flekto\Postcode\Api\Data\UpdateNotificationInterface;
use Magento\Framework\Model\AbstractModel;

class UpdateNotification extends AbstractModel implements UpdateNotificationInterface
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\UpdateNotification::class);
    }

    public function getVersion(): string
    {
        return $this->getData(self::VERSION);
    }

    public function setVersion(string $version): UpdateNotification
    {
        return $this->setData(self::VERSION, $version);
    }

    public function getNotified(): bool
    {
        return $this->getData(self::NOTIFIED);
    }

    public function setNotified(bool $notified): UpdateNotification
    {
        return $this->setData(self::NOTIFIED, $notified);
    }
}
