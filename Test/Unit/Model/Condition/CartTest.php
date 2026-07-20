<?php
/**
 * Magendoo CustomerSegment - shopping cart condition test
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model\Condition;

use Magendoo\CustomerSegment\Model\Condition\Cart;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Rule\Model\Condition\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CartTest extends TestCase
{
    /** @var Context&MockObject */
    private $context;

    /** @var ResourceConnection&MockObject */
    private $resourceConnection;

    /** @var AdapterInterface&MockObject */
    private $connection;

    /** @var Select&MockObject */
    private $select;

    private Cart $cart;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->createMock(Select::class);

        $this->cart = new Cart(
            $this->context,
            $this->resourceConnection
        );
    }

    /**
     * Wire the connection, a fluent Select mock, and (optionally) the active-quote row and its SKUs.
     */
    private function stubConnection(array $fetchRowResult = [], array $fetchColResult = []): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(fn ($table) => $table);
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn($fetchRowResult);
        $this->connection->method('fetchCol')->willReturn($fetchColResult);

        foreach (['from', 'columns', 'where', 'order', 'limit', 'distinct', 'join'] as $method) {
            $this->select->method($method)->willReturnSelf();
        }
    }

    public function testLoadAttributeOptionsSetsExpectedAttributes(): void
    {
        $result = $this->cart->loadAttributeOptions();
        $this->assertSame($this->cart, $result);

        $options = $this->cart->getAttributeOption();
        foreach (
            ['cart_subtotal', 'cart_items_count', 'cart_products', 'has_active_cart', 'cart_last_activity'] as $key
        ) {
            $this->assertArrayHasKey($key, $options);
        }
    }

    public function testGetInputTypeReturnsPriceForCartSubtotal(): void
    {
        $this->cart->setAttribute('cart_subtotal');
        $this->assertEquals('price', $this->cart->getInputType());
    }

    public function testGetInputTypeReturnsNumericForCartItemsCount(): void
    {
        $this->cart->setAttribute('cart_items_count');
        $this->assertEquals('numeric', $this->cart->getInputType());
    }

    public function testGetInputTypeReturnsNumericForCartLastActivity(): void
    {
        $this->cart->setAttribute('cart_last_activity');
        $this->assertEquals('numeric', $this->cart->getInputType());
    }

    public function testGetInputTypeReturnsSelectForHasActiveCart(): void
    {
        $this->cart->setAttribute('has_active_cart');
        $this->assertEquals('select', $this->cart->getInputType());
    }

    public function testGetInputTypeReturnsStringForDefault(): void
    {
        $this->cart->setAttribute('cart_products');
        $this->assertEquals('string', $this->cart->getInputType());
    }

    public function testGetValueElementTypeReturnsSelectForHasActiveCart(): void
    {
        $this->cart->setAttribute('has_active_cart');
        $this->assertEquals('select', $this->cart->getValueElementType());
    }

    public function testGetValueElementTypeReturnsTextForDefault(): void
    {
        $this->cart->setAttribute('cart_subtotal');
        $this->assertEquals('text', $this->cart->getValueElementType());
    }

    public function testGetValueSelectOptionsReturnsYesNoForHasActiveCart(): void
    {
        $this->cart->setAttribute('has_active_cart');
        $options = $this->cart->getValueSelectOptions();

        $this->assertCount(2, $options);
        $this->assertEquals('1', $options[0]['value']);
        $this->assertEquals('0', $options[1]['value']);
    }

    public function testGetValueSelectOptionsReturnsEmptyForOtherAttributes(): void
    {
        $this->cart->setAttribute('cart_subtotal');
        $this->assertEmpty($this->cart->getValueSelectOptions());
    }

    public function testGetDefaultOperatorOptionsForHasActiveCart(): void
    {
        $this->cart->setAttribute('has_active_cart');
        $operators = $this->cart->getDefaultOperatorOptions();

        $this->assertArrayHasKey('==', $operators);
        $this->assertCount(1, $operators);
    }

    public function testGetDefaultOperatorOptionsForNumeric(): void
    {
        $this->cart->setAttribute('cart_items_count');
        $operators = $this->cart->getDefaultOperatorOptions();

        foreach (['==', '>', '<'] as $op) {
            $this->assertArrayHasKey($op, $operators);
        }
    }

    public function testGetDefaultOperatorOptionsForProducts(): void
    {
        $this->cart->setAttribute('cart_products');
        $operators = $this->cart->getDefaultOperatorOptions();

        foreach (['==', '!=', '{}', '!{}'] as $op) {
            $this->assertArrayHasKey($op, $operators);
        }
    }

    public function testValidateSubtotalGreaterThan(): void
    {
        $this->stubConnection(
            ['entity_id' => 1, 'subtotal' => 100.00, 'items_count' => 2, 'updated_at' => '2026-07-20 00:00:00'],
            ['SKU123']
        );

        $this->cart->setAttribute('cart_subtotal');
        $this->cart->setOperator('>');
        $this->cart->setValue(50);

        $this->assertTrue($this->cart->validate(1));
    }

    public function testValidateWithCustomerObject(): void
    {
        $customerModel = $this->createMock(\Magento\Customer\Model\Customer::class);
        $customerModel->method('getId')->willReturn(1);

        $this->stubConnection(['entity_id' => 1, 'subtotal' => 150.00, 'items_count' => 1], []);

        $this->cart->setAttribute('cart_subtotal');
        $this->cart->setOperator('>=');
        $this->cart->setValue(100);

        $this->assertTrue($this->cart->validate($customerModel));
    }

    public function testValidateReturnsFalseForInvalidInput(): void
    {
        $this->assertFalse($this->cart->validate('not-a-valid-customer'));
    }

    public function testValidateHasActiveCartYes(): void
    {
        $this->stubConnection(
            ['entity_id' => 1, 'subtotal' => 50.00, 'items_count' => 1, 'updated_at' => '2026-07-20 00:00:00'],
            ['SKU1']
        );

        $this->cart->setAttribute('has_active_cart');
        $this->cart->setOperator('==');
        $this->cart->setValue('1');

        $this->assertTrue($this->cart->validate(1));
    }

    public function testValidateHasActiveCartNoWhenNoQuote(): void
    {
        $this->stubConnection([], []);

        $this->cart->setAttribute('has_active_cart');
        $this->cart->setOperator('==');
        $this->cart->setValue('0');

        $this->assertTrue($this->cart->validate(1));
    }

    public function testValidateCartProductsContains(): void
    {
        $this->stubConnection(
            ['entity_id' => 1, 'subtotal' => 100.00, 'items_count' => 2, 'updated_at' => '2026-07-20 00:00:00'],
            ['SKU123', 'ABC456']
        );

        $this->cart->setAttribute('cart_products');
        $this->cart->setOperator('{}');
        $this->cart->setValue('SKU');

        $this->assertTrue($this->cart->validate(1));
    }

    public function testValidateCartProductsEqualsNotFound(): void
    {
        $this->stubConnection(
            ['entity_id' => 1, 'subtotal' => 100.00, 'items_count' => 1, 'updated_at' => '2026-07-20 00:00:00'],
            ['SKU123']
        );

        $this->cart->setAttribute('cart_products');
        $this->cart->setOperator('==');
        $this->cart->setValue('NOTFOUND');

        $this->assertFalse($this->cart->validate(1));
    }

    /**
     * "does not contain X" is TRUE when no cart line matches (negation at the cart level).
     */
    public function testValidateCartProductsDoesNotContainIsTrueWhenAbsent(): void
    {
        $this->stubConnection(
            ['entity_id' => 1, 'subtotal' => 100.00, 'items_count' => 1, 'updated_at' => '2026-07-20 00:00:00'],
            ['SKU123']
        );

        $this->cart->setAttribute('cart_products');
        $this->cart->setOperator('!{}');
        $this->cart->setValue('ZZZ');

        $this->assertTrue($this->cart->validate(1));
    }

    /**
     * "does not contain X" is FALSE when a line DOES match.
     */
    public function testValidateCartProductsDoesNotContainIsFalseWhenPresent(): void
    {
        $this->stubConnection(
            ['entity_id' => 1, 'subtotal' => 100.00, 'items_count' => 1, 'updated_at' => '2026-07-20 00:00:00'],
            ['SKU123']
        );

        $this->cart->setAttribute('cart_products');
        $this->cart->setOperator('!{}');
        $this->cart->setValue('SKU');

        $this->assertFalse($this->cart->validate(1));
    }

    public function testValidateCartItemsCount(): void
    {
        $this->stubConnection(['entity_id' => 1, 'items_count' => 5], []);

        $this->cart->setAttribute('cart_items_count');
        $this->cart->setOperator('>');
        $this->cart->setValue(3);

        $this->assertTrue($this->cart->validate(1));
    }

    public function testGetMatchingCustomerIdsResolvesHasActiveCartYes(): void
    {
        $this->stubConnection([], ['4', '8', '15']);

        $this->cart->setAttribute('has_active_cart');
        $this->cart->setOperator('==');
        $this->cart->setValue('1');

        $this->assertSame([4, 8, 15], $this->cart->getMatchingCustomerIds());
    }

    public function testGetMatchingCustomerIdsReturnsNullForHasActiveCartNo(): void
    {
        $this->cart->setAttribute('has_active_cart');
        $this->cart->setOperator('==');
        $this->cart->setValue('0');

        $this->assertNull($this->cart->getMatchingCustomerIds());
    }

    public function testGetMatchingCustomerIdsReturnsNullForNonResolvableAttribute(): void
    {
        $this->cart->setAttribute('cart_subtotal');
        $this->cart->setOperator('>');
        $this->cart->setValue(10);

        $this->assertNull($this->cart->getMatchingCustomerIds());
    }
}
