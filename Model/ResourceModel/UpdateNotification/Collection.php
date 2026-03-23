<?php

namespace PostcodeEu\AddressValidation\Model\ResourceModel\UpdateNotification;

use PostcodeEu\AddressValidation\Model\UpdateNotification;
use PostcodeEu\AddressValidation\Model\ResourceModel\UpdateNotification as UpdateNotificationResourceModel;
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
