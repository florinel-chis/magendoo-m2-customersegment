<?php
/**
 * Magendoo CustomerSegment Order Condition Test
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model\Condition;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Rule\Model\Condition\Context;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magendoo\CustomerSegment\Model\Condition\Order;

class OrderTest extends TestCase
{
    /** @var Context|MockObject */
    private $context;

    /** @var OrderCollectionFactory|MockObject */
    private $orderCollectionFactory;

    /** @var ResourceConnection|MockObject */
    private $resourceConnection;

    /** @var AdapterInterface|MockObject */
    private $connection;

    /** @var Select|MockObject */
    private $select;

    /** @var Order */
    private $order;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->orderCollectionFactory = $this->createMock(OrderCollectionFactory::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->createMock(Select::class);
        
        // Create SUT once in setUp
        $this->order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
    }

    /**
     * Helper to reduce DB mock boilerplate duplication
     */
    private function setupDbMock(array $fetchRowResult): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturn('sales_order');
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn($fetchRowResult);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('columns')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
    }

    public function testLoadAttributeOptionsSetsExpectedAttributes(): void
    {
        $result = $this->order->loadAttributeOptions();
        $this->assertSame($this->order, $result);
        
        $options = $this->order->getAttributeOption();
        $this->assertIsArray($options);
        $this->assertArrayHasKey('total_orders', $options);
        $this->assertArrayHasKey('total_revenue', $options);
        $this->assertArrayHasKey('average_order_value', $options);
        $this->assertArrayHasKey('first_order_date', $options);
        $this->assertArrayHasKey('last_order_date', $options);
        $this->assertArrayHasKey('total_items', $options);
    }

    public function testGetInputTypeReturnsNumericForTotalOrders(): void
    {
        $this->order->setAttribute('total_orders');
        $this->assertEquals('numeric', $this->order->getInputType());
    }

    public function testGetInputTypeReturnsNumericForTotalItems(): void
    {
        $this->order->setAttribute('total_items');
        $this->assertEquals('numeric', $this->order->getInputType());
    }

    public function testGetInputTypeReturnsPriceForTotalRevenue(): void
    {
        $this->order->setAttribute('total_revenue');
        $this->assertEquals('price', $this->order->getInputType());
    }

    public function testGetInputTypeReturnsPriceForAverageOrderValue(): void
    {
        $this->order->setAttribute('average_order_value');
        $this->assertEquals('price', $this->order->getInputType());
    }

    public function testGetInputTypeReturnsDateForFirstOrderDate(): void
    {
        $this->order->setAttribute('first_order_date');
        $this->assertEquals('date', $this->order->getInputType());
    }

    public function testGetInputTypeReturnsDateForLastOrderDate(): void
    {
        $this->order->setAttribute('last_order_date');
        $this->assertEquals('date', $this->order->getInputType());
    }

    public function testGetInputTypeReturnsSelectForPaymentMethod(): void
    {
        $this->order->setAttribute('payment_method');
        $this->assertEquals('select', $this->order->getInputType());
    }

    public function testGetInputTypeReturnsStringForDefault(): void
    {
        $this->order->setAttribute('used_coupon');
        $this->assertEquals('string', $this->order->getInputType());
    }

    public function testGetValueElementTypeReturnsTextForNumeric(): void
    {
        $this->order->setAttribute('total_orders');
        $this->assertEquals('text', $this->order->getValueElementType());
    }

    public function testGetValueElementTypeReturnsDateForDateAttribute(): void
    {
        $this->order->setAttribute('first_order_date');
        $this->assertEquals('date', $this->order->getValueElementType());
    }

    public function testGetValueElementTypeReturnsSelectForSelectAttributes(): void
    {
        $this->order->setAttribute('payment_method');
        $this->assertEquals('select', $this->order->getValueElementType());
    }

    public function testGetDefaultOperatorOptionsForNumeric(): void
    {
        $this->order->setAttribute('total_orders');
        $operators = $this->order->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('>', $operators);
        $this->assertArrayHasKey('<', $operators);
        $this->assertArrayHasKey('>=', $operators);
        $this->assertArrayHasKey('<=', $operators);
    }

    public function testGetDefaultOperatorOptionsForDate(): void
    {
        $this->order->setAttribute('first_order_date');
        $operators = $this->order->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('>', $operators);
        $this->assertArrayHasKey('<', $operators);
    }

    public function testGetDefaultOperatorOptionsForSelect(): void
    {
        $this->order->setAttribute('payment_method');
        $operators = $this->order->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('()', $operators);
        $this->assertArrayHasKey('!()', $operators);
    }

    public function testValidateWithNumericCustomerId(): void
    {
        $this->setupDbMock([
            'total_orders' => 5,
            'total_revenue' => 1000,
        ]);

        $this->order->setAttribute('total_orders');
        $this->order->setOperator('>');
        $this->order->setValue(3);

        $result = $this->order->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithCustomerObject(): void
    {
        $customerModel = $this->createMock(\Magento\Customer\Model\Customer::class);
        $customerModel->method('getId')->willReturn(1);

        $this->setupDbMock(['total_revenue' => 150.00]);

        $this->order->setAttribute('total_revenue');
        $this->order->setOperator('>=');
        $this->order->setValue(100);

        $result = $this->order->validate($customerModel);
        $this->assertTrue($result);
    }

    public function testValidateReturnsFalseForInvalidInput(): void
    {
        $result = $this->order->validate('not-a-valid-customer');
        $this->assertFalse($result);
    }

    public function testValidateReturnsFalseWhenNoOrders(): void
    {
        $this->setupDbMock([]);

        // Test with non-total_orders attribute to trigger early return
        $this->order->setAttribute('total_revenue');
        $this->order->setOperator('>');
        $this->order->setValue(0);

        $result = $this->order->validate(1);
        $this->assertFalse($result);
    }

    public function testValidateWithTotalOrdersAttribute(): void
    {
        $this->setupDbMock(['total_orders' => 5]);

        $this->order->setAttribute('total_orders');
        $this->order->setOperator('==');
        $this->order->setValue(5);

        $result = $this->order->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithTotalRevenueAttribute(): void
    {
        $this->setupDbMock(['total_revenue' => 1000.50]);

        $this->order->setAttribute('total_revenue');
        $this->order->setOperator('>=');
        $this->order->setValue(500);

        $result = $this->order->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateReturnsFalseForNullValue(): void
    {
        $this->setupDbMock(['total_orders' => null]);

        $this->order->setAttribute('total_orders');
        $this->order->setOperator('>');
        $this->order->setValue(0);

        $result = $this->order->validate(1);
        $this->assertFalse($result);
    }
}
