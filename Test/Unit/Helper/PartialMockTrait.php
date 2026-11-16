<?php

declare(strict_types=1);

namespace PostcodeEu\AddressValidation\Test\Unit\Helper;

use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionProperty;

/**
 * Partial mocks that may include magic (DataObject __call) methods, across PHPUnit versions.
 *
 * Magento 2.4.9+ ships Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait for this,
 * but older Magento versions (PHPUnit 10) don't have it. PHPUnit 12 removed MockBuilder::addMethods(),
 * so on older PHPUnit we use addMethods(), and otherwise the same reflection approach as Magento's trait.
 */
trait PartialMockTrait
{
    /**
     * Create a mock with only the given methods mocked, including methods that don't exist in the class.
     *
     * @param string $className
     * @param string[] $methods
     * @return MockObject
     */
    protected function createPartialMockWithReflection(string $className, array $methods): MockObject
    {
        $builder = $this->getMockBuilder($className)->disableOriginalConstructor();

        if (method_exists(MockBuilder::class, 'addMethods')) {
            // PHPUnit < 12: existing methods must use onlyMethods(), magic methods addMethods().
            // Skip empty lists: onlyMethods([]) would make getMock() discard the added methods.
            $existing = array_values(array_filter(
                $methods,
                static fn (string $method): bool => method_exists($className, $method)
            ));
            $magic = array_values(array_diff($methods, $existing));

            if ($existing !== []) {
                $builder->onlyMethods($existing);
            }
            if ($magic !== []) {
                $builder->addMethods($magic);
            }

            return $builder->getMock();
        }

        // PHPUnit 12+: set the builder's method list directly, which also accepts non-existing methods.
        (new ReflectionProperty($builder, 'methods'))->setValue($builder, $methods);

        return $builder->getMock();
    }
}
