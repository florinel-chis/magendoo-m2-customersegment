<?php
/**
 * Magendoo CustomerSegment Cart Condition Test
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model\Condition;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Rule\Model\Condition\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magendoo\CustomerSegment\Model\Condition\Cart;

class CartTest extends TestCase
{
    /** @var Context|MockObject */
    private $context;

    /** @var QuoteCollectionFactory|MockObject */
    private $quoteCollectionFactory;

    /** @var ResourceConnection|MockObject */
    private $resourceConnection;

    /** @var CheckoutSession|MockObject */
    private $checkoutSession;

    /** @var AdapterInterface|MockObject */
    private $connection;

    /** @var Select|MockObject */
    private $select;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->quoteCollectionFactory = $this->createMock(QuoteCollectionFactory::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->createMock(Select::class);
    }

    public function testLoadAttributeOptionsSetsExpectedAttributes(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $result = $cart->loadAttributeOptions();
        $this->assertSame($cart, $result);
        
        $options = $cart->getAttributeOption();
        $this->assertIsArray($options);
        $this->assertArrayHasKey('cart_subtotal', $options);
        $this->assertArrayHasKey('cart_items_count', $options);
        $this->assertArrayHasKey('cart_products', $options);
        $this->assertArrayHasKey('has_active_cart', $options);
        $this->assertArrayHasKey('cart_last_activity', $options);
    }

    public function testGetInputTypeReturnsPriceForCartSubtotal(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_subtotal');
        $this->assertEquals('price', $cart->getInputType());
    }

    public function testGetInputTypeReturnsNumericForCartItemsCount(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_items_count');
        $this->assertEquals('numeric', $cart->getInputType());
    }

    public function testGetInputTypeReturnsNumericForCartLastActivity(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_last_activity');
        $this->assertEquals('numeric', $cart->getInputType());
    }

    public function testGetInputTypeReturnsSelectForHasActiveCart(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('has_active_cart');
        $this->assertEquals('select', $cart->getInputType());
    }

    public function testGetInputTypeReturnsStringForDefault(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_products');
        $this->assertEquals('string', $cart->getInputType());
    }

    public function testGetValueElementTypeReturnsSelectForHasActiveCart(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('has_active_cart');
        $this->assertEquals('select', $cart->getValueElementType());
    }

    public function testGetValueElementTypeReturnsTextForDefault(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_subtotal');
        $this->assertEquals('text', $cart->getValueElementType());
    }

    public function testGetValueSelectOptionsReturnsYesNoForHasActiveCart(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('has_active_cart');
        $options = $cart->getValueSelectOptions();
        
        $this->assertIsArray($options);
        $this->assertCount(2, $options);
        $this->assertEquals('1', $options[0]['value']);
        $this->assertEquals('0', $options[1]['value']);
    }

    public function testGetValueSelectOptionsReturnsEmptyForOtherAttributes(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_subtotal');
        $options = $cart->getValueSelectOptions();
        
        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }

    public function testGetDefaultOperatorOptionsForHasActiveCart(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('has_active_cart');
        $operators = $cart->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertCount(1, $operators);
    }

    public function testGetDefaultOperatorOptionsForNumeric(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_items_count');
        $operators = $cart->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('>', $operators);
        $this->assertArrayHasKey('<', $operators);
    }

    public function testGetDefaultOperatorOptionsForDefault(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_products');
        $operators = $cart->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('{}', $operators);
        $this->assertArrayHasKey('!{}', $operators);
    }

    public function testValidateWithNumericCustomerId(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(function($table) {
            return $table;
        });
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn([
            'entity_id' => 1,
            'subtotal' => 100.00,
            'items_count' => 2,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->connection->method('fetchCol')->willReturn(['SKU123']);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();
        $this->select->method('limit')->willReturnSelf();

        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_subtotal');
        $cart->setOperator('>');
        $cart->setValue(50);

        $result = $cart->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithCustomerObject(): void
    {
        $customerModel = $this->createMock(\Magento\Customer\Model\Customer::class);
        $customerModel->method('getId')->willReturn(1);

        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(function($table) {
            return $table;
        });
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn([
            'entity_id' => 1,
            'subtotal' => 150.00,
        ]);
        $this->connection->method('fetchCol')->willReturn([]);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();
        $this->select->method('limit')->willReturnSelf();

        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_subtotal');
        $cart->setOperator('>=');
        $cart->setValue(100);

        $result = $cart->validate($customerModel);
        $this->assertTrue($result);
    }

    public function testValidateReturnsFalseForInvalidInput(): void
    {
        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        
        $result = $cart->validate('not-a-valid-customer');
        $this->assertFalse($result);
    }

    public function testValidateWithHasActiveCartYes(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(function($table) {
            return $table;
        });
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn([
            'entity_id' => 1,
            'subtotal' => 50.00,
            'items_count' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->connection->method('fetchCol')->willReturn(['SKU1']);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();
        $this->select->method('limit')->willReturnSelf();

        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('has_active_cart');
        $cart->setOperator('==');
        $cart->setValue('1');

        $result = $cart->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithHasActiveCartNo(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(function($table) {
            return $table;
        });
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn(false);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();
        $this->select->method('limit')->willReturnSelf();

        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('has_active_cart');
        $cart->setOperator('==');
        $cart->setValue('0');

        $result = $cart->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithCartProductsContains(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(function($table) {
            return $table;
        });
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn([
            'entity_id' => 1,
            'subtotal' => 100.00,
            'items_count' => 2,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->connection->method('fetchCol')->willReturn(['SKU123', 'ABC456']);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();
        $this->select->method('limit')->willReturnSelf();

        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_products');
        $cart->setOperator('{}');
        $cart->setValue('SKU');

        $result = $cart->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateReturnsFalseWhenProductNotFound(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(function($table) {
            return $table;
        });
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn([
            'entity_id' => 1,
            'subtotal' => 100.00,
        ]);
        $this->connection->method('fetchCol')->willReturn(['SKU123']);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();
        $this->select->method('limit')->willReturnSelf();

        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_products');
        $cart->setOperator('==');
        $cart->setValue('NOTFOUND');

        $result = $cart->validate(1);
        $this->assertFalse($result);
    }

    public function testValidateWithCartItemsCount(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(function($table) {
            return $table;
        });
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn([
            'entity_id' => 1,
            'items_count' => 5,
        ]);
        $this->connection->method('fetchCol')->willReturn([]);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();
        $this->select->method('limit')->willReturnSelf();

        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_items_count');
        $cart->setOperator('>');
        $cart->setValue(3);

        $result = $cart->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithCartLastActivity(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(function($table) {
            return $table;
        });
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn([
            'entity_id' => 1,
            'updated_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
        ]);
        $this->connection->method('fetchCol')->willReturn([]);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();
        $this->select->method('limit')->willReturnSelf();

        $cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection
        );
        $cart->setAttribute('cart_last_activity');
        $cart->setOperator('<');
        $cart->setValue(10);

        $result = $cart->validate(1);
        $this->assertTrue($result);
    }
}
