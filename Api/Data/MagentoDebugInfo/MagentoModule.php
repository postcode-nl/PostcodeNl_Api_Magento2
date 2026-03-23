<?php

namespace PostcodeEu\AddressValidation\Api\Data\MagentoDebugInfo;

class MagentoModule implements MagentoModuleInterface
{
    private string $name;
    private string $setupVersion;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->name = $data['name'] ?? '';
        $this->setupVersion = $data['setup_version'] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getSetupVersion(): string
    {
        return $this->setupVersion;
    }
}
