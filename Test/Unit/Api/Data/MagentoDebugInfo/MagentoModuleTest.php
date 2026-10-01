<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Api\Data\MagentoDebugInfo;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Api\Data\MagentoDebugInfo\MagentoModule;

/**
 * Module name/version defaults for MagentoModule.
 */
class MagentoModuleTest extends TestCase
{
    #[Test]
    public function provided_name_and_setup_version_returned(): void
    {
        $module = new MagentoModule([
            'name' => 'PostcodeEu_AddressValidation',
            'setup_version' => '1.2.3',
        ]);

        $this->assertSame('PostcodeEu_AddressValidation', $module->getName());
        $this->assertSame('1.2.3', $module->getSetupVersion());
    }

    #[Test]
    public function missing_values_default_to_empty_strings(): void
    {
        $module = new MagentoModule([]);

        $this->assertSame('', $module->getName());
        $this->assertSame('', $module->getSetupVersion());
    }
}
