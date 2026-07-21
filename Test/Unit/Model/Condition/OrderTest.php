<?php
/**
 * Magendoo CustomerSegment - order history condition test
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model\Condition;

use Magendoo\CustomerSegment\Model\Condition\Order;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Rule\Model\Condition\Context;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    /** @var Context&MockObject */
    private $context;

    /** @var OrderCollectionFactory&MockObject */
    private $orderCollectionFactory;

    /** @var ResourceConnection&MockObject */
    private $resourceConnection;

    /** @var AdapterInterface&MockObject */
    private $connection;

    /** @var Select&MockObject */
    private $select;

    private Order $order;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->orderCollectionFactory = $this->createMock(OrderCollectionFactory::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->createMock(Select::class);

        $this->order = new Order(
            $this->context,
            $this->orderCollectionFactory,
            $this->resourceConnection
        );
    }

    /**
     * Wire up the connection + a fully-fluent Select mock. Fetch results are set per test.
     */
    private function stubConnection(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(fn ($table) => $table);
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('quote')->willReturnCallback(fn ($v) => "'" . $v . "'");
        $this->connection->method('quoteInto')->willReturnCallback(fn ($t, $v) => $t . $v);

        foreach (['from', 'columns', 'where', 'reset', 'distinct', 'join', 'limit', 'order'] as $method) {
            $this->select->method($method)->willReturnSelf();
        }
    }

    public function testLoadAttributeOptionsSetsExpectedAttributes(): void
    {
        $result = $this->order->loadAttributeOptions();
        $this->assertSame($this->order, $result);

        $options = $this->order->getAttributeOption();
        foreach (
            [
                'total_orders', 'total_revenue', 'average_order_value',
                'first_order_date', 'last_order_date', 'total_items',
            ] as $key
        ) {
            $this->assertArrayHasKey($key, $options);
        }
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

        foreach (['==', '!=', '>', '<', '>=', '<='] as $op) {
            $this->assertArrayHasKey($op, $operators);
        }
    }

    public function testGetDefaultOperatorOptionsForDate(): void
    {
        $this->order->setAttribute('first_order_date');
        $operators = $this->order->getDefaultOperatorOptions();

        foreach (['==', '!=', '>', '<'] as $op) {
            $this->assertArrayHasKey($op, $operators);
        }
    }

    public function testGetDefaultOperatorOptionsForSelect(): void
    {
        $this->order->setAttribute('payment_method');
        $operators = $this->order->getDefaultOperatorOptions();

        foreach (['==', '!=', '()', '!()'] as $op) {
            $this->assertArrayHasKey($op, $operators);
        }
    }

    public function testValidateAggregateGreaterThan(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchRow')->willReturn(['total_orders' => 5]);

        $this->order->setAttribute('total_orders');
        $this->order->setOperator('>');
        $this->order->setValue(3);

        $this->assertTrue($this->order->validate(1));
    }

    public function testValidateWithCustomerObject(): void
    {
        $customerModel = $this->createMock(\Magento\Customer\Model\Customer::class);
        $customerModel->method('getId')->willReturn(1);

        $this->stubConnection();
        $this->connection->method('fetchRow')->willReturn(['total_revenue' => 150.00]);

        $this->order->setAttribute('total_revenue');
        $this->order->setOperator('>=');
        $this->order->setValue(100);

        $this->assertTrue($this->order->validate($customerModel));
    }

    public function testValidateReturnsFalseForInvalidInput(): void
    {
        $this->assertFalse($this->order->validate('not-a-valid-customer'));
    }

    /**
     * Zero-order customer: COALESCE-ed revenue is 0, so "revenue > 0" is FALSE.
     */
    public function testValidateRevenueGreaterThanIsFalseForZeroOrderCustomer(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchRow')->willReturn([]);

        $this->order->setAttribute('total_revenue');
        $this->order->setOperator('>');
        $this->order->setValue(0);

        $this->assertFalse($this->order->validate(1));
    }

    /**
     * Zero-order customer: "revenue < 100" is TRUE (0 < 100).
     */
    public function testValidateRevenueLessThanIsTrueForZeroOrderCustomer(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchRow')->willReturn([]);

        $this->order->setAttribute('total_revenue');
        $this->order->setOperator('<');
        $this->order->setValue(100);

        $this->assertTrue($this->order->validate(1));
    }

    public function testValidateExistencePositiveMatch(): void
    {
        $this->stubConnection();
        // A matching order row exists.
        $this->connection->method('fetchOne')->willReturn('42');

        $this->order->setAttribute('payment_method');
        $this->order->setOperator('==');
        $this->order->setValue('checkmo');

        $this->assertTrue($this->order->validate(1));
    }

    /**
     * Negation is applied at the customer level: a customer with NO matching order
     * satisfies "payment_method != checkmo".
     */
    public function testValidateExistenceNegationTrueWhenNoMatchingOrder(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchOne')->willReturn(false);

        $this->order->setAttribute('payment_method');
        $this->order->setOperator('!=');
        $this->order->setValue('checkmo');

        $this->assertTrue($this->order->validate(1));
    }

    /**
     * A customer who DOES have a matching order does NOT satisfy the negation.
     */
    public function testValidateExistenceNegationFalseWhenMatchingOrderExists(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchOne')->willReturn('7');

        $this->order->setAttribute('used_coupon');
        $this->order->setOperator('!=');
        $this->order->setValue('SAVE10');

        $this->assertFalse($this->order->validate(1));
    }

    public function testGetMatchingCustomerIdsReturnsNullForAggregateAttribute(): void
    {
        $this->order->setAttribute('total_orders');
        $this->order->setOperator('>');
        $this->order->setValue(1);

        $this->assertNull($this->order->getMatchingCustomerIds());
    }

    public function testGetMatchingCustomerIdsReturnsNullForNegativeOperator(): void
    {
        $this->order->setAttribute('payment_method');
        $this->order->setOperator('!=');
        $this->order->setValue('checkmo');

        $this->assertNull($this->order->getMatchingCustomerIds());
    }

    public function testGetMatchingCustomerIdsResolvesExistenceSet(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchCol')->willReturn(['3', '9']);

        $this->order->setAttribute('payment_method');
        $this->order->setOperator('==');
        $this->order->setValue('checkmo');

        $this->assertSame([3, 9], $this->order->getMatchingCustomerIds());
    }
}
