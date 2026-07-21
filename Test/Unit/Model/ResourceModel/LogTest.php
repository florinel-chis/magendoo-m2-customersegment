<?php
/**
 * Magendoo CustomerSegment - Log resource model unit tests
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model\ResourceModel;

use Magendoo\CustomerSegment\Model\ResourceModel\Log;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Log::class)]
class LogTest extends TestCase
{
    private const TABLE = 'magendoo_customer_segment_log';

    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    /** @var Log&MockObject */
    private Log $resource;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);

        $this->resource = $this->getMockBuilder(Log::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->resource->method('getMainTable')->willReturn(self::TABLE);

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2026-07-20 10:00:00');
        $ref = new \ReflectionProperty(Log::class, 'dateTime');
        $ref->setAccessible(true);
        $ref->setValue($this->resource, $dateTime);
    }

    #[Test]
    public function logInsertsRowWithUtcTimestamp(): void
    {
        $this->connection->expects($this->once())->method('insert')
            ->with(self::TABLE, [
                'segment_id' => 12,
                'action' => 'refresh',
                'details' => 'Refreshed via cron — 8 customers',
                'created_at' => '2026-07-20 10:00:00',
            ]);

        $this->resource->log(12, 'refresh', 'Refreshed via cron — 8 customers');
    }

    #[Test]
    public function logAllowsNullDetails(): void
    {
        $this->connection->expects($this->once())->method('insert')
            ->with(self::TABLE, [
                'segment_id' => 3,
                'action' => 'created',
                'details' => null,
                'created_at' => '2026-07-20 10:00:00',
            ]);

        $this->resource->log(3, 'created');
    }
}
