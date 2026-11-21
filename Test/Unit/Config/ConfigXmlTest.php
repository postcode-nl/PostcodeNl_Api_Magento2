<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Module configuration declarations that cannot be exercised through code alone.
 */
class ConfigXmlTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../../..';

    #[Test]
    public function residence_line_countries_have_expected_default(): void
    {
        $config = $this->loadXml('etc/config.xml');

        $this->assertSame(
            'IT,ES,FR',
            (string)$config->default->postcodenl_api->advanced_config->residence_line_countries
        );
    }

    #[Test]
    public function residence_line_countries_have_no_admin_field(): void
    {
        $system = $this->loadXml('etc/adminhtml/system.xml');
        $fields = $system->xpath(
            '//section[@id="postcodenl_api"]//field[@id="residence_line_countries"]'
        );

        $this->assertSame([], $fields);
    }

    private function loadXml(string $path): \SimpleXMLElement
    {
        $file = self::MODULE_ROOT . '/' . $path;
        $xml = simplexml_load_file($file);

        $this->assertNotFalse($xml, sprintf('Could not load %s.', $file));

        return $xml;
    }
}
