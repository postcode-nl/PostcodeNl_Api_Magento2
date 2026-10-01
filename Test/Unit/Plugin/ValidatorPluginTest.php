<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Plugin;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Validator;
use Magento\Framework\Validator\Constraint;
use Magento\Framework\Validator\ValidatorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Model\Validator\City;
use PostcodeEu\AddressValidation\Plugin\ValidatorPlugin;

/**
 * Version-gated swap of the city validator alias.
 */
class ValidatorPluginTest extends TestCase
{
    #[Test]
    #[DataProvider('breakChainProvider')]
    public function matching_version_and_alias_swap_in_city_validator(
        string $version,
        bool $breakChainOnFailure
    ): void {
        $plugin = $this->createPlugin($version);

        $result = $plugin->beforeAddValidator(
            $this->createStub(Validator::class),
            $this->createConstraint('city_validator'),
            $breakChainOnFailure
        );

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(City::class, $result[0]);
        $this->assertSame($breakChainOnFailure, $result[1]);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function breakChainProvider(): array
    {
        return [
            '2.4.8 default false' => ['2.4.8', false],
            '2.4.8 break chain true' => ['2.4.8', true],
            '2.4.8-p1' => ['2.4.8-p1', false],
            '2.4.8.1' => ['2.4.8.1', false],
        ];
    }

    #[Test]
    #[DataProvider('otherVersionProvider')]
    public function other_versions_leave_arguments_untouched(string $version): void
    {
        $plugin = $this->createPlugin($version);

        $result = $plugin->beforeAddValidator(
            $this->createStub(Validator::class),
            $this->createConstraint('city_validator')
        );

        $this->assertNull($result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherVersionProvider(): array
    {
        return [
            'newer' => ['2.4.9'],
            'older' => ['2.4.7'],
            '2.4.8 prefix but not the 2.4.8 line' => ['2.4.80'],
        ];
    }

    #[Test]
    public function other_alias_leaves_arguments_untouched(): void
    {
        $plugin = $this->createPlugin('2.4.8');

        $result = $plugin->beforeAddValidator(
            $this->createStub(Validator::class),
            $this->createConstraint('other_validator')
        );

        $this->assertNull($result);
    }

    #[Test]
    public function validator_without_alias_leaves_arguments_untouched(): void
    {
        $plugin = $this->createPlugin('2.4.8');

        $result = $plugin->beforeAddValidator(
            $this->createStub(Validator::class),
            $this->createStub(ValidatorInterface::class)
        );

        $this->assertNull($result);
    }

    private function createPlugin(string $version): ValidatorPlugin
    {
        $productMetadata = $this->createStub(ProductMetadataInterface::class);
        $productMetadata->method('getVersion')->willReturn($version);

        return new ValidatorPlugin($productMetadata);
    }

    private function createConstraint(string $alias): Constraint
    {
        $constraint = $this->createStub(Constraint::class);
        $constraint->method('getAlias')->willReturn($alias);

        return $constraint;
    }
}
