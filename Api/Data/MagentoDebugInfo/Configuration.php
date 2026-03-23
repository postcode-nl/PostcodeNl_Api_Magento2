<?php

namespace PostcodeEu\AddressValidation\Api\Data\MagentoDebugInfo;

class Configuration implements ConfigurationInterface
{
    protected $key;
    protected $secret;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->key = $data['key'] ?? '';
        $this->secret = $data['secret'] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @inheritdoc
     */
    public function getSecret(): string
    {
        return $this->secret;
    }
}
