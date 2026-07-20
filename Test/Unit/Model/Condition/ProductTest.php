<?php
/**
 * Magendoo CustomerSegment - product interactions condition test
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model\Condition;

use Magendoo\CustomerSegment\Model\Condition\Product;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Rule\Model\Condition\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    /** @var Context&MockObject */
    private $context;

    /** @var ResourceConnection&MockObject */
    private $resourceConnection;

    /** @var AdapterInterface&MockObject */
    private $connection;

    /** @var Select&MockObject */
    private $select;

    private Product $product;

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
     * Wire the connection and a fully-fluent Select mock. Fetch results are set per test.
     */
    private function stubConnection(): void
    {
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(fn ($table) => $table);
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('quoteInto')->willReturnCallback(fn ($t, $v) => str_replace('?', "'" . $v . "'", $t));

        foreach (['from', 'join', 'where', 'columns', 'distinct'] as $method) {
            $this->select->method($method)->willReturnSelf();
        }
    }

    public function testLoadAttributeOptionsSetsExpectedAttributes(): void
    {
        $result = $this->product->loadAttributeOptions();
        $this->assertSame($this->product, $result);

        $options = $this->product->getAttributeOption();
        foreach (['purchased_products', 'purchased_categories', 'wishlist_items_count'] as $key) {
            $this->assertArrayHasKey($key, $options);
        }
        // viewed_categories was a doc-only stub feature that the refactor dropped.
        $this->assertArrayNotHasKey('viewed_categories', $options);
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

        foreach (['==', '!=', '>', '<'] as $op) {
            $this->assertArrayHasKey($op, $operators);
        }
    }

    public function testGetDefaultOperatorOptionsForString(): void
    {
        $this->product->setAttribute('purchased_products');
        $operators = $this->product->getDefaultOperatorOptions();

        foreach (['==', '!=', '{}', '!{}'] as $op) {
            $this->assertArrayHasKey($op, $operators);
        }
    }

    public function testGetDefaultOperatorOptionsForCategories(): void
    {
        $this->product->setAttribute('purchased_categories');
        $operators = $this->product->getDefaultOperatorOptions();

        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
    }

    public function testValidateReturnsFalseForInvalidInput(): void
    {
        $this->assertFalse($this->product->validate('not-a-valid-customer'));
    }

    public function testValidateWishlistItemsCountGreaterThan(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchOne')->willReturn('5');

        $this->product->setAttribute('wishlist_items_count');
        $this->product->setOperator('>');
        $this->product->setValue(3);

        $this->assertTrue($this->product->validate(1));
    }

    public function testValidatePurchasedProductsEquals(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchOne')->willReturn('1');

        $this->product->setAttribute('purchased_products');
        $this->product->setOperator('==');
        $this->product->setValue('SKU123');

        $this->assertTrue($this->product->validate(1));
    }

    public function testValidatePurchasedCategoriesEquals(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchOne')->willReturn('2');

        $this->product->setAttribute('purchased_categories');
        $this->product->setOperator('==');
        $this->product->setValue('10,20');

        $this->assertTrue($this->product->validate(1));
    }

    public function testValidatePurchasedProductsEqualsNoMatch(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchOne')->willReturn('0');

        $this->product->setAttribute('purchased_products');
        $this->product->setOperator('==');
        $this->product->setValue('NONEXISTENT');

        $this->assertFalse($this->product->validate(1));
    }

    /**
     * "did NOT purchase X" is TRUE when the customer has no matching purchase (customer-level negation).
     */
    public function testValidatePurchasedProductsNotEqualsTrueWhenAbsent(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchOne')->willReturn('0');

        $this->product->setAttribute('purchased_products');
        $this->product->setOperator('!=');
        $this->product->setValue('SKU123');

        $this->assertTrue($this->product->validate(1));
    }

    /**
     * "did NOT purchase X" is FALSE when the customer DID purchase it.
     */
    public function testValidatePurchasedProductsNotEqualsFalseWhenPresent(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchOne')->willReturn('3');

        $this->product->setAttribute('purchased_products');
        $this->product->setOperator('!=');
        $this->product->setValue('SKU123');

        $this->assertFalse($this->product->validate(1));
    }

    public function testValidateWithCustomerObject(): void
    {
        $customerModel = $this->createMock(\Magento\Customer\Model\Customer::class);
        $customerModel->method('getId')->willReturn(1);

        $this->stubConnection();
        $this->connection->method('fetchOne')->willReturn('3');

        $this->product->setAttribute('wishlist_items_count');
        $this->product->setOperator('==');
        $this->product->setValue(3);

        $this->assertTrue($this->product->validate($customerModel));
    }

    public function testGetMatchingCustomerIdsResolvesPurchasedProducts(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchCol')->willReturn(['2', '6']);

        $this->product->setAttribute('purchased_products');
        $this->product->setOperator('==');
        $this->product->setValue('SKU123');

        $this->assertSame([2, 6], $this->product->getMatchingCustomerIds());
    }

    public function testGetMatchingCustomerIdsResolvesPurchasedCategories(): void
    {
        $this->stubConnection();
        $this->connection->method('fetchCol')->willReturn(['11']);

        $this->product->setAttribute('purchased_categories');
        $this->product->setOperator('==');
        $this->product->setValue('10,20');

        $this->assertSame([11], $this->product->getMatchingCustomerIds());
    }

    public function testGetMatchingCustomerIdsReturnsNullForNegativeOperator(): void
    {
        $this->product->setAttribute('purchased_products');
        $this->product->setOperator('!=');
        $this->product->setValue('SKU123');

        $this->assertNull($this->product->getMatchingCustomerIds());
    }

    public function testGetMatchingCustomerIdsReturnsNullForWishlist(): void
    {
        $this->product->setAttribute('wishlist_items_count');
        $this->product->setOperator('>');
        $this->product->setValue(1);

        $this->assertNull($this->product->getMatchingCustomerIds());
    }
}
