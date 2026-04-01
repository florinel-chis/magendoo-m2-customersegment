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

    /** @var Cart */
    private $cart;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->quoteCollectionFactory = $this->createMock(QuoteCollectionFactory::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->createMock(Select::class);
        
        // Create SUT once in setUp
        $this->cart = new Cart(
            $this->context,
            $this->quoteCollectionFactory,
            $this->resourceConnection,
            [],
            $this->checkoutSession
        );
    }

    /**
     * Helper to reduce DB mock boilerplate duplication
     */
    private function setupDbMock(array $fetchRowResult, array $fetchColResult = []): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(fn($table) => $table);
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchRow')->willReturn($fetchRowResult);
        $this->connection->method('fetchCol')->willReturn($fetchColResult);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('columns')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();
        $this->select->method('limit')->willReturnSelf();
    }

    public function testLoadAttributeOptionsSetsExpectedAttributes(): void
    {
        $result = $this->cart->loadAttributeOptions();
        $this->assertSame($this->cart, $result);
        
        $options = $this->cart->getAttributeOption();
        $this->assertIsArray($options);
        $this->assertArrayHasKey('cart_subtotal', $options);
        $this->assertArrayHasKey('cart_items_count', $options);
        $this->assertArrayHasKey('cart_products', $options);
        $this->assertArrayHasKey('has_active_cart', $options);
        $this->assertArrayHasKey('cart_last_activity', $options);
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
        
        $this->assertIsArray($options);
        $this->assertCount(2, $options);
        $this->assertEquals('1', $options[0]['value']);
        $this->assertEquals('0', $options[1]['value']);
    }

    public function testGetValueSelectOptionsReturnsEmptyForOtherAttributes(): void
    {
        $this->cart->setAttribute('cart_subtotal');
        $options = $this->cart->getValueSelectOptions();
        
        $this->assertIsArray($options);
        $this->assertEmpty($options);
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
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('>', $operators);
        $this->assertArrayHasKey('<', $operators);
    }

    public function testGetDefaultOperatorOptionsForDefault(): void
    {
        $this->cart->setAttribute('cart_products');
        $operators = $this->cart->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('{}', $operators);
        $this->assertArrayHasKey('!{}', $operators);
    }

    public function testValidateWithNumericCustomerId(): void
    {
        $this->setupDbMock([
            'entity_id' => 1,
            'subtotal' => 100.00,
            'items_count' => 2,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['SKU123']);

        $this->cart->setAttribute('cart_subtotal');
        $this->cart->setOperator('>');
        $this->cart->setValue(50);

        $result = $this->cart->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithCustomerObject(): void
    {
        $customerModel = $this->createMock(\Magento\Customer\Model\Customer::class);
        $customerModel->method('getId')->willReturn(1);

        $this->setupDbMock([
            'entity_id' => 1,
            'subtotal' => 150.00,
        ], []);

        $this->cart->setAttribute('cart_subtotal');
        $this->cart->setOperator('>=');
        $this->cart->setValue(100);

        $result = $this->cart->validate($customerModel);
        $this->assertTrue($result);
    }

    public function testValidateReturnsFalseForInvalidInput(): void
    {
        $result = $this->cart->validate('not-a-valid-customer');
        $this->assertFalse($result);
    }

    public function testValidateWithHasActiveCartYes(): void
    {
        $this->setupDbMock([
            'entity_id' => 1,
            'subtotal' => 50.00,
            'items_count' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['SKU1']);

        $this->cart->setAttribute('has_active_cart');
        $this->cart->setOperator('==');
        $this->cart->setValue('1');

        $result = $this->cart->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithHasActiveCartNo(): void
    {
        $this->setupDbMock([], []);

        $this->cart->setAttribute('has_active_cart');
        $this->cart->setOperator('==');
        $this->cart->setValue('0');

        $result = $this->cart->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithCartProductsContains(): void
    {
        $this->setupDbMock([
            'entity_id' => 1,
            'subtotal' => 100.00,
            'items_count' => 2,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['SKU123', 'ABC456']);

        $this->cart->setAttribute('cart_products');
        $this->cart->setOperator('{}');
        $this->cart->setValue('SKU');

        $result = $this->cart->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateReturnsFalseWhenProductNotFound(): void
    {
        $this->setupDbMock([
            'entity_id' => 1,
            'subtotal' => 100.00,
        ], ['SKU123']);

        $this->cart->setAttribute('cart_products');
        $this->cart->setOperator('==');
        $this->cart->setValue('NOTFOUND');

        $result = $this->cart->validate(1);
        $this->assertFalse($result);
    }

    public function testValidateWithCartItemsCount(): void
    {
        $this->setupDbMock([
            'entity_id' => 1,
            'items_count' => 5,
        ], []);

        $this->cart->setAttribute('cart_items_count');
        $this->cart->setOperator('>');
        $this->cart->setValue(3);

        $result = $this->cart->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithCartLastActivity(): void
    {
        $this->setupDbMock([
            'entity_id' => 1,
            'updated_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
        ], []);

        $this->cart->setAttribute('cart_last_activity');
        $this->cart->setOperator('<');
        $this->cart->setValue(10);

        $result = $this->cart->validate(1);
        $this->assertTrue($result);
    }
}
