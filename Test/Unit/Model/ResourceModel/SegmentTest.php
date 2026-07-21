<?php
/**
 * Magendoo CustomerSegment - Segment resource model unit tests
 *
 * Focuses on the atomic membership replace (replaceCustomers wraps delete-all +
 * bulk insert in a single transaction) and the mass-assign / count helpers.
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model\ResourceModel;

use Magendoo\CustomerSegment\Model\ResourceModel\Segment as ResourceSegment;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceSegment::class)]
class SegmentTest extends TestCase
{
    private const CUSTOMER_TABLE = 'magendoo_customer_segment_customer';

    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    /** @var ResourceSegment&MockObject */
    private ResourceSegment $resource;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);

        // AbstractDb needs a live Context/ResourceConnection to construct, so build the
        // resource with the constructor disabled and stub the DB seams it actually uses.
        $this->resource = $this->getMockBuilder(ResourceSegment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getTable'])
            ->getMock();
        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->resource->method('getTable')->willReturnArgument(0);

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2026-07-20 10:00:00');
        $ref = new \ReflectionProperty(ResourceSegment::class, 'dateTime');
        $ref->setAccessible(true);
        $ref->setValue($this->resource, $dateTime);
    }

    #[Test]
    public function replaceCustomersRunsInsideOneTransactionThatCommits(): void
    {
        $order = [];
        $this->connection->expects($this->once())->method('beginTransaction')
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'begin';
                return $this->connection;
            });
        $this->connection->expects($this->once())->method('delete')
            ->with(self::CUSTOMER_TABLE, ['segment_id = ?' => 55])
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'delete';
                return 3;
            });
        $this->connection->expects($this->once())->method('insertOnDuplicate')
            ->willReturnCallback(function ($table, $rows) use (&$order) {
                $order[] = 'insert';
                $this->assertSame(self::CUSTOMER_TABLE, $table);
                $this->assertCount(2, $rows);
                $this->assertSame(55, $rows[0]['segment_id']);
                $this->assertSame(101, $rows[0]['customer_id']);
                $this->assertSame('2026-07-20 10:00:00', $rows[0]['assigned_at']);
                return 2;
            });
        $this->connection->method('select')->willReturn($this->makeCountSelect(2));
        $this->connection->method('fetchOne')->willReturn('2');
        $this->connection->expects($this->once())->method('commit')
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'commit';
                return $this->connection;
            });
        $this->connection->expects($this->never())->method('rollBack');

        $count = $this->resource->replaceCustomers(55, [101, 102]);

        $this->assertSame(2, $count);
        $this->assertSame(['begin', 'delete', 'insert', 'commit'], $order);
    }

    #[Test]
    public function replaceCustomersWithEmptySetDeletesAllAndSkipsInsert(): void
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('delete')
            ->with(self::CUSTOMER_TABLE, ['segment_id = ?' => 7]);
        $this->connection->expects($this->never())->method('insertOnDuplicate');
        $this->connection->method('select')->willReturn($this->makeCountSelect(0));
        $this->connection->method('fetchOne')->willReturn('0');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');

        $this->assertSame(0, $this->resource->replaceCustomers(7, []));
    }

    #[Test]
    public function replaceCustomersRollsBackAndRethrowsOnFailure(): void
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->method('delete')->willThrowException(new \RuntimeException('boom'));
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $this->resource->replaceCustomers(7, [1, 2]);
    }

    #[Test]
    public function massAssignCustomersReturnsTrueMembershipCountNotAttemptedInserts(): void
    {
        // Two supplied ids but the true count comes from countSegmentCustomers.
        $this->connection->expects($this->once())->method('insertOnDuplicate')
            ->with(self::CUSTOMER_TABLE, $this->isArray(), ['assigned_at']);
        $this->connection->method('select')->willReturn($this->makeCountSelect(5));
        $this->connection->method('fetchOne')->willReturn('5');

        $this->assertSame(5, $this->resource->massAssignCustomers(9, [1, 2]));
    }

    #[Test]
    public function massAssignCustomersWithEmptyListSkipsInsertAndCounts(): void
    {
        $this->connection->expects($this->never())->method('insertOnDuplicate');
        $this->connection->method('select')->willReturn($this->makeCountSelect(4));
        $this->connection->method('fetchOne')->willReturn('4');

        $this->assertSame(4, $this->resource->massAssignCustomers(9, []));
    }

    #[Test]
    public function countSegmentCustomersReturnsInteger(): void
    {
        $this->connection->method('select')->willReturn($this->makeCountSelect(12));
        $this->connection->method('fetchOne')->willReturn('12');

        $this->assertSame(12, $this->resource->countSegmentCustomers(3));
    }

    #[Test]
    public function removeAllCustomersDeletesBySegment(): void
    {
        $this->connection->expects($this->once())->method('delete')
            ->with(self::CUSTOMER_TABLE, ['segment_id = ?' => 8])
            ->willReturn(6);

        $this->assertSame(6, $this->resource->removeAllCustomers(8));
    }

    #[Test]
    public function getCustomerSegmentIdsReturnsIntArray(): void
    {
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchCol')->willReturn(['1', '2', '3']);

        $this->assertSame([1, 2, 3], $this->resource->getCustomerSegmentIds(4));
    }

    #[Test]
    public function updateCustomerCountWritesCountAndTimestamp(): void
    {
        $this->connection->expects($this->once())->method('update')
            ->with(
                'magendoo_customer_segment',
                ['customer_count' => 42, 'last_refreshed' => '2026-07-20 10:00:00'],
                ['segment_id = ?' => 2]
            )
            ->willReturn(1);

        $this->assertTrue($this->resource->updateCustomerCount(2, 42));
    }

    /**
     * A Select stub whose from()/where() chain returns itself.
     *
     * @param int $count
     * @return \Magento\Framework\DB\Select&MockObject
     */
    private function makeCountSelect(int $count): \Magento\Framework\DB\Select
    {
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        return $select;
    }
}
