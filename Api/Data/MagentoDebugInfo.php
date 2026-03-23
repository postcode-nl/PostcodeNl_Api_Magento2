<?php

namespace PostcodeEu\AddressValidation\Api\Data;

class MagentoDebugInfo implements MagentoDebugInfoInterface
{
    protected $moduleVersion;
    protected $magentoVersion;
    protected $client;
    protected $session;
    protected $configuration;
    protected $modules;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->moduleVersion = $data['moduleVersion'] ?? '';
        $this->magentoVersion = $data['magentoVersion'] ?? '';
        $this->client = $data['client'] ?? '';
        $this->session = $data['session'] ?? '';
        $this->configuration = new MagentoDebugInfo\Configuration($data['configuration'] ?? []);
        $this->modules = $data['modules'] ?? [];
    }

    /**
     * @inheritdoc
     */
    public function getModuleVersion(): string
    {
        return $this->moduleVersion;
    }

    /**
     * @inheritdoc
     */
    public function getMagentoVersion(): string
    {
        return $this->magentoVersion;
    }

    /**
     * @inheritdoc
     */
    public function getClient(): string
    {
        return $this->client;
    }

    /**
     * @inheritdoc
     */
    public function getSession(): string
    {
        return $this->session;
    }

    /**
     * @inheritdoc
     */
    public function getConfiguration(): MagentoDebugInfo\ConfigurationInterface
    {
        return $this->configuration;
    }

    /**
     * @inheritdoc
     */
    public function getModules(): array
    {
        return $this->modules;
    }
}
