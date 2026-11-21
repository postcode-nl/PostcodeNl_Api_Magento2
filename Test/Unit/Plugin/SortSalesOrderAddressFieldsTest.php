<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Plugin;

use Magento\Customer\Model\Metadata\Form;
use Magento\Framework\App\Request\Http;
use Magento\Framework\DataObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Plugin\SortSalesOrderAddressFields;

/**
 * Address field sort-order override on the sales order create form.
 */
class SortSalesOrderAddressFieldsTest extends TestCase
{
    private const INITIAL_SORT_ORDERS = [
        'country_id' => 10,
        'street' => 20,
        'postcode' => 30,
        'city' => 40,
        'region' => 50,
        'region_id' => 60,
    ];

    private const ORDER_CREATE_SORT_ORDERS = [
        'country_id' => 70,
        'street' => 80,
        'postcode' => 90,
        'city' => 100,
        'region' => 110,
        'region_id' => 110,
    ];

    #[Test]
    public function flag_off_leaves_sort_orders_untouched(): void
    {
        $plugin = $this->createPlugin('sales_order_create', false);
        $attributes = $this->createAttributes();

        $result = $plugin->afterGetAttributes($this->createStub(Form::class), $attributes);

        $this->assertSortOrders(self::INITIAL_SORT_ORDERS, $result);
    }

    #[Test]
    public function non_order_create_action_leaves_sort_orders_untouched(): void
    {
        $plugin = $this->createPlugin('customer_address_edit', true);
        $attributes = $this->createAttributes();

        $result = $plugin->afterGetAttributes($this->createStub(Form::class), $attributes);

        $this->assertSortOrders(self::INITIAL_SORT_ORDERS, $result);
    }

    #[Test]
    #[DataProvider('orderCreateActionProvider')]
    public function order_create_action_sets_expected_sort_orders(string $actionName): void
    {
        $plugin = $this->createPlugin($actionName, true);
        $attributes = $this->createAttributes();

        $result = $plugin->afterGetAttributes($this->createStub(Form::class), $attributes);

        $this->assertSortOrders(self::ORDER_CREATE_SORT_ORDERS, $result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function orderCreateActionProvider(): array
    {
        return [
            'form' => ['sales_order_create'],
            'index' => ['sales_order_create_index'],
        ];
    }

    #[Test]
    public function missing_country_id_reorders_remaining_fields(): void
    {
        $initial = [
            'street' => 20,
            'postcode' => 30,
            'city' => 40,
            'region' => 50,
            'region_id' => 60,
        ];
        $plugin = $this->createPlugin('sales_order_create', true);
        $attributes = $this->createAttributes($initial);

        $result = $plugin->afterGetAttributes($this->createStub(Form::class), $attributes);

        $this->assertSortOrders([
            'street' => 80,
            'postcode' => 90,
            'city' => 100,
            'region' => 110,
            'region_id' => 110,
        ], $result);
    }

    /**
     * @param array<string, int> $sortOrders
     * @return array<string, DataObject>
     */
    private function createAttributes(array $sortOrders = self::INITIAL_SORT_ORDERS): array
    {
        $attributes = [];

        foreach ($sortOrders as $code => $sortOrder) {
            $attributes[$code] = new DataObject(['sort_order' => $sortOrder]);
        }

        return $attributes;
    }

    /**
     * @param array<string, int> $expected
     * @param array<string, DataObject> $result
     */
    private function assertSortOrders(array $expected, array $result): void
    {
        foreach ($expected as $code => $sortOrder) {
            $this->assertSame($sortOrder, $result[$code]->getData('sort_order'), $code);
        }
    }

    private function createPlugin(string $actionName, bool $flag): SortSalesOrderAddressFields
    {
        $request = $this->createStub(Http::class);
        $request->method('getFullActionName')->willReturn($actionName);

        $storeConfigHelper = $this->createMock(StoreConfigHelper::class);
        $storeConfigHelper->expects($this->once())
            ->method('isSetFlag')
            ->with('change_fields_position')
            ->willReturn($flag);

        return new SortSalesOrderAddressFields($request, $storeConfigHelper);
    }
}
