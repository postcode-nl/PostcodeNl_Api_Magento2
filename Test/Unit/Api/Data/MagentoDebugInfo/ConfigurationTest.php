<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Api\Data\MagentoDebugInfo;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Api\Data\MagentoDebugInfo\Configuration;

/**
 * Credential defaults for Configuration.
 */
class ConfigurationTest extends TestCase
{
    #[Test]
    public function provided_credentials_returned(): void
    {
        $configuration = new Configuration(['key' => 'api-key', 'secret' => 'api-secret']);

        $this->assertSame('api-key', $configuration->getKey());
        $this->assertSame('api-secret', $configuration->getSecret());
    }

    #[Test]
    public function missing_credentials_default_to_empty_strings(): void
    {
        $configuration = new Configuration([]);

        $this->assertSame('', $configuration->getKey());
        $this->assertSame('', $configuration->getSecret());
    }
}
