<?php
/**
 * Magendoo CustomerSegment SqlBuilder Test
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magendoo\CustomerSegment\Model\SqlBuilder;

class SqlBuilderTest extends TestCase
{
    /** @var ResourceConnection|MockObject */
    private $resourceConnection;

    /** @var AdapterInterface|MockObject */
    private $connection;

    /** @var Select|MockObject */
    private $select;

    /** @var SqlBuilder */
    private $sqlBuilder;

    protected function setUp(): void
    {
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->createMock(Select::class);

        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(fn($table) => $table);
        $this->connection->method('select')->willReturn($this->select);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('join')->willReturnSelf();
        $this->select->method('group')->willReturnSelf();
        $this->select->method('having')->willReturnSelf();

        $this->sqlBuilder = new SqlBuilder($this->resourceConnection);
    }

    public function testBuildBatchValidationQueryForCustomerEntity(): void
    {
        $customerIds = [1, 2, 3];
        $attribute = 'email';
        $operator = '==';
        $value = 'test@example.com';

        $select = $this->sqlBuilder->buildBatchValidationQuery(
            $customerIds,
            $attribute,
            $operator,
            $value,
            'customer'
        );

        $this->assertInstanceOf(Select::class, $select);
    }

    public function testBuildBatchValidationQueryForOrderEntity(): void
    {
        $customerIds = [1, 2, 3];
        $attribute = 'total_orders';
        $operator = '>';
        $value = 5;

        $select = $this->sqlBuilder->buildBatchValidationQuery(
            $customerIds,
            $attribute,
            $operator,
            $value,
            'order'
        );

        $this->assertInstanceOf(Select::class, $select);
    }

    public function testBuildBatchValidationQueryReturnsEmptyQueryForUnknownEntity(): void
    {
        $customerIds = [1, 2, 3];

        $select = $this->sqlBuilder->buildBatchValidationQuery(
            $customerIds,
            'attribute',
            '==',
            'value',
            'unknown'
        );

        $this->assertInstanceOf(Select::class, $select);
    }

    public function testValidateBatchReturnsMatchingCustomerIds(): void
    {
        $customerIds = [1, 2, 3];
        $expectedResult = [1, 3];

        $this->connection->method('fetchCol')->willReturn($expectedResult);

        $result = $this->sqlBuilder->validateBatch(
            $customerIds,
            'email',
            '==',
            'test@example.com',
            'customer'
        );

        $this->assertEquals($expectedResult, $result);
    }

    public function testBuildOrderAggregateQueryForTotalOrders(): void
    {
        $customerIds = [1, 2, 3];

        $select = $this->sqlBuilder->buildBatchValidationQuery(
            $customerIds,
            'total_orders',
            '>',
            5,
            'order'
        );

        $this->assertInstanceOf(Select::class, $select);
    }

    public function testBuildOrderAggregateQueryForUnknownAttributeReturnsEmpty(): void
    {
        $customerIds = [1, 2, 3];

        $select = $this->sqlBuilder->buildBatchValidationQuery(
            $customerIds,
            'unknown_attribute',
            '==',
            'value',
            'order'
        );

        $this->assertInstanceOf(Select::class, $select);
    }

    public function testValidateBatchReturnsEmptyArrayForNoMatches(): void
    {
        $customerIds = [1, 2, 3];

        $this->connection->method('fetchCol')->willReturn([]);

        $result = $this->sqlBuilder->validateBatch(
            $customerIds,
            'email',
            '==',
            'nonexistent@example.com',
            'customer'
        );

        $this->assertEquals([], $result);
    }
}
