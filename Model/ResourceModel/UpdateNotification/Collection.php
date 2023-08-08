<?php

namespace Flekto\Postcode\Model\ResourceModel\UpdateNotification;

use Flekto\Postcode\Model\UpdateNotification;
use Flekto\Postcode\Model\ResourceModel\UpdateNotification as UpdateNotificationResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(
            UpdateNotification::class,
            UpdateNotificationResourceModel::class
        );
    }
}
