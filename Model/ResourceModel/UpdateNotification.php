<?php

namespace PostcodeEu\AddressValidation\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class UpdateNotification extends AbstractDb
{
    public const MAIN_TABLE = 'postcodenl_update_notification';
    public const ID_FIELD = 'id';

    protected function _construct(): void
    {
        $this->_init(self::MAIN_TABLE, self::ID_FIELD);
    }
}
