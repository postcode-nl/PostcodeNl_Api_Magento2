<?php

namespace PostcodeEu\AddressValidation\Api\Data;

/**
 * @api
 */
interface UpdateNotificationInterface
{
    public const VERSION = 'version';
    public const NOTIFIED = 'notified';

    /**
     * @return string
     */
    public function getVersion();

    /**
     * @param string $version
     * @return $this
     */
    public function setVersion(string $version);

    /**
     * @return bool
     */
    public function getNotified();

    /**
     * @param bool $notified
     * @return $this
     */
    public function setNotified(bool $notified);
}
