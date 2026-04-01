<?php
/**
 * Magendoo CustomerSegment Product Condition Test
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magendoo\CustomerSegment\Model\Condition\Product;

class ProductTest extends TestCase
{
    /** @var Context|MockObject */
    private $context;

    /** @var ResourceConnection|MockObject */
    private $resourceConnection;

    /** @var AdapterInterface|MockObject */
    private $connection;

    /** @var Select|MockObject */
    private $select;

    /** @var Product */
    private $product;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->createMock(Select::class);

        $this->product = new Product(
            $this->context,
            $this->resourceConnection
        );
    }

    /**
     * Helper to reduce DB mock boilerplate
     * @param mixed $fetchResult
     */
    private function setupDbMock(mixed $fetchResult): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(fn($table) => $table);
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchOne')->willReturn($fetchResult);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('join')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
    }

    public function testLoadAttributeOptionsSetsExpectedAttributes(): void
    {
        $result = $this->product->loadAttributeOptions();
        $this->assertSame($this->product, $result);

        $options = $this->product->getAttributeOption();
        $this->assertIsArray($options);
        $this->assertArrayHasKey('viewed_categories', $options);
        $this->assertArrayHasKey('purchased_products', $options);
        $this->assertArrayHasKey('purchased_categories', $options);
        $this->assertArrayHasKey('wishlist_items_count', $options);
    }

    public function testGetInputTypeReturnsNumericForWishlistItemsCount(): void
    {
        $this->product->setAttribute('wishlist_items_count');
        $this->assertEquals('numeric', $this->product->getInputType());
    }

    public function testGetInputTypeReturnsStringForDefault(): void
    {
        $this->product->setAttribute('purchased_products');
        $this->assertEquals('string', $this->product->getInputType());
    }

    public function testGetDefaultOperatorOptionsForNumeric(): void
    {
        $this->product->setAttribute('wishlist_items_count');
        $operators = $this->product->getDefaultOperatorOptions();

        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('>', $operators);
        $this->assertArrayHasKey('<', $operators);
    }

    public function testGetDefaultOperatorOptionsForString(): void
    {
        $this->product->setAttribute('purchased_products');
        $operators = $this->product->getDefaultOperatorOptions();

        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('{}', $operators);
        $this->assertArrayHasKey('!{}', $operators);
    }

    public function testValidateReturnsFalseForInvalidInput(): void
    {
        $result = $this->product->validate('not-a-valid-customer');
        $this->assertFalse($result);
    }

    public function testValidateWithWishlistItemsCount(): void
    {
        $this->setupDbMock(5); // 5 items in wishlist

        $this->product->setAttribute('wishlist_items_count');
        $this->product->setOperator('>');
        $this->product->setValue(3);

        $result = $this->product->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithPurchasedProductsEquals(): void
    {
        $this->setupDbMock(1); // 1 product matching

        $this->product->setAttribute('purchased_products');
        $this->product->setOperator('==');
        $this->product->setValue('SKU123');

        $result = $this->product->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithPurchasedCategories(): void
    {
        $this->setupDbMock(2); // 2 products from category

        $this->product->setAttribute('purchased_categories');
        $this->product->setOperator('==');
        $this->product->setValue('10,20');

        $result = $this->product->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateReturnsFalseWhenNoMatch(): void
    {
        $this->setupDbMock(0); // No products matching

        $this->product->setAttribute('purchased_products');
        $this->product->setOperator('==');
        $this->product->setValue('NONEXISTENT');

        $result = $this->product->validate(1);
        $this->assertFalse($result);
    }

    public function testValidateWithCustomerObject(): void
    {
        $customerModel = $this->createMock(\Magento\Customer\Model\Customer::class);
        $customerModel->method('getId')->willReturn(1);

        $this->setupDbMock(3);

        $this->product->setAttribute('wishlist_items_count');
        $this->product->setOperator('==');
        $this->product->setValue(3);

        $result = $this->product->validate($customerModel);
        $this->assertTrue($result);
    }
}
