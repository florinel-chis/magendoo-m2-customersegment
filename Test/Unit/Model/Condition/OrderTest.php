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
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Asset\Repository as AssetRepo;
use Magento\Framework\View\LayoutInterface;
use Magento\Rule\Model\Condition\Context;
use Magento\Rule\Model\ConditionFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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

    protected function setUp(): void
    {
        // Create full Context mock with all required dependencies
        $assetRepo = $this->createMock(AssetRepo::class);
        $localeDate = $this->createMock(TimezoneInterface::class);
        $localeDate->method('getConfigTimezone')->willReturn('UTC');
        $layout = $this->createMock(LayoutInterface::class);
        $conditionFactory = $this->createMock(ConditionFactory::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->context = new Context($assetRepo, $localeDate, $layout, $conditionFactory, $logger);
        $this->orderCollectionFactory = $this->createMock(OrderCollectionFactory::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->createMock(Select::class);
    }

    public function testLoadAttributeOptionsSetsExpectedAttributes(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $result = $order->loadAttributeOptions();
        $this->assertSame($order, $result);
        
        $options = $order->getAttributeOption();
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
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('total_orders');
        $this->assertEquals('numeric', $order->getInputType());
    }

    public function testGetInputTypeReturnsNumericForTotalItems(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('total_items');
        $this->assertEquals('numeric', $order->getInputType());
    }

    public function testGetInputTypeReturnsPriceForTotalRevenue(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('total_revenue');
        $this->assertEquals('price', $order->getInputType());
    }

    public function testGetInputTypeReturnsPriceForAverageOrderValue(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('average_order_value');
        $this->assertEquals('price', $order->getInputType());
    }

    public function testGetInputTypeReturnsDateForFirstOrderDate(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('first_order_date');
        $this->assertEquals('date', $order->getInputType());
    }

    public function testGetInputTypeReturnsDateForLastOrderDate(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('last_order_date');
        $this->assertEquals('date', $order->getInputType());
    }

    public function testGetInputTypeReturnsSelectForPaymentMethod(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('payment_method');
        $this->assertEquals('select', $order->getInputType());
    }

    public function testGetInputTypeReturnsStringForDefault(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('used_coupon');
        $this->assertEquals('string', $order->getInputType());
    }

    public function testGetValueElementTypeReturnsTextForNumeric(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('total_orders');
        $this->assertEquals('text', $order->getValueElementType());
    }

    public function testGetValueElementTypeReturnsDateForDateAttribute(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('first_order_date');
        $this->assertEquals('date', $order->getValueElementType());
    }

    public function testGetValueElementTypeReturnsSelectForSelectAttributes(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('payment_method');
        $this->assertEquals('select', $order->getValueElementType());
    }

    public function testGetDefaultOperatorOptionsForNumeric(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('total_orders');
        $operators = $order->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('>', $operators);
        $this->assertArrayHasKey('<', $operators);
        $this->assertArrayHasKey('>=', $operators);
        $this->assertArrayHasKey('<=', $operators);
    }

    public function testGetDefaultOperatorOptionsForDate(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('first_order_date');
        $operators = $order->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('>', $operators);
        $this->assertArrayHasKey('<', $operators);
    }

    public function testGetDefaultOperatorOptionsForSelect(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('payment_method');
        $operators = $order->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('()', $operators);
        $this->assertArrayHasKey('!()', $operators);
    }

    public function testValidateWithNumericCustomerId(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturn('sales_order');
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn([
            'total_orders' => 5,
            'total_revenue' => 1000,
        ]);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('columns')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();

        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('total_orders');
        $order->setOperator('>');
        $order->setValue(3);

        $result = $order->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithCustomerObject(): void
    {
        $customerModel = $this->createMock(\Magento\Customer\Model\Customer::class);
        $customerModel->method('getId')->willReturn(1);

        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturn('sales_order');
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn([
            'total_revenue' => 150.00,
        ]);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('columns')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();

        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('total_revenue');
        $order->setOperator('>=');
        $order->setValue(100);

        $result = $order->validate($customerModel);
        $this->assertTrue($result);
    }

    public function testValidateReturnsFalseForInvalidInput(): void
    {
        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        
        $result = $order->validate('not-a-valid-customer');
        $this->assertFalse($result);
    }

    public function testValidateReturnsFalseWhenNoOrders(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturn('sales_order');
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn([]);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('columns')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();

        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('total_orders');
        $order->setOperator('>');
        $order->setValue(0);

        $result = $order->validate(1);
        $this->assertFalse($result);
    }

    public function testValidateWithTotalOrdersAttribute(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturn('sales_order');
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn(['total_orders' => 5]);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('columns')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();

        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('total_orders');
        $order->setOperator('==');
        $order->setValue(5);

        $result = $order->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithTotalRevenueAttribute(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturn('sales_order');
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn(['total_revenue' => 1000.50]);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('columns')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();

        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('total_revenue');
        $order->setOperator('>=');
        $order->setValue(500);

        $result = $order->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateReturnsFalseForNullValue(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturn('sales_order');
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn(['total_orders' => null]);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('columns')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();

        $order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
        $order->setAttribute('total_orders');
        $order->setOperator('>');
        $order->setValue(0);

        $result = $order->validate(1);
        $this->assertFalse($result);
    }
}
