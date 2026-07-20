<?php
/**
 * Magendoo CustomerSegment - Scheduled refresh cron unit tests
 *
 * Covers the enable gate (C4), per-segment since-last-run due evaluation and the
 * activity-log write on refresh (C5).
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Cron;

use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Cron\RefreshSegments;
use Magendoo\CustomerSegment\Helper\Data as Helper;
use Magendoo\CustomerSegment\Model\ResourceModel\Log as LogResource;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment\Collection;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment\CollectionFactory;
use Magendoo\CustomerSegment\Model\Segment;
use Magento\Cron\Model\Schedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(RefreshSegments::class)]
class RefreshSegmentsTest extends TestCase
{
    /** GMT epoch, minute-aligned, corresponds to 2027-01-15 08:00:00 UTC. */
    private const NOW_TS = 1800000000;

    /** @var SegmentManagementInterface&MockObject */
    private SegmentManagementInterface $segmentManagement;

    /** @var CollectionFactory&MockObject */
    private CollectionFactory $collectionFactory;

    /** @var ScheduleFactory&MockObject */
    private ScheduleFactory $scheduleFactory;

    /** @var DateTime&MockObject */
    private DateTime $dateTime;

    /** @var Helper&MockObject */
    private Helper $helper;

    /** @var LogResource&MockObject */
    private LogResource $logResource;

    /** @var RefreshSegments */
    private RefreshSegments $cron;

    protected function setUp(): void
    {
        $this->segmentManagement = $this->createMock(SegmentManagementInterface::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->scheduleFactory = $this->createMock(ScheduleFactory::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->helper = $this->createMock(Helper::class);
        $this->logResource = $this->createMock(LogResource::class);

        $this->dateTime->method('gmtTimestamp')->willReturn(self::NOW_TS);
        $this->dateTime->method('gmtDate')->willReturn('2027-01-15 08:00:00');

        $this->cron = new RefreshSegments(
            $this->segmentManagement,
            $this->collectionFactory,
            $this->scheduleFactory,
            $this->dateTime,
            $this->helper,
            $this->logResource,
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * @param string $cronExpr
     * @param string $lastRefreshed
     * @return Segment&MockObject
     */
    private function segment(string $cronExpr, string $lastRefreshed, int $id = 1): Segment
    {
        $segment = $this->getMockBuilder(Segment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getName', 'getData'])
            ->getMock();
        $segment->method('getId')->willReturn($id);
        $segment->method('getName')->willReturn('Segment ' . $id);
        $segment->method('getData')->willReturnCallback(
            static function (string $key = '') use ($cronExpr, $lastRefreshed) {
                return match ($key) {
                    'cron_expression' => $cronExpr,
                    'last_refreshed' => $lastRefreshed,
                    default => null,
                };
            }
        );

        return $segment;
    }

    /**
     * @param Segment[] $segments
     * @return Collection&MockObject
     */
    private function collectionOf(array $segments): Collection
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addActiveFilter')->willReturnSelf();
        $collection->method('addRefreshModeFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(count($segments));
        $collection->method('getIterator')->willReturn(new \ArrayIterator($segments));
        $this->collectionFactory->method('create')->willReturn($collection);

        return $collection;
    }

    /**
     * Schedule stub whose trySchedule always matches (i.e. the expression is "due now").
     */
    private function stubScheduleMatches(): void
    {
        // setCronExpr/setScheduledAt are magic DataObject setters — leave them real and
        // stub only trySchedule (a real method) to always match "due now".
        $schedule = $this->getMockBuilder(Schedule::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['trySchedule'])
            ->getMock();
        $schedule->method('trySchedule')->willReturn(true);
        $this->scheduleFactory->method('create')->willReturn($schedule);
    }

    #[Test]
    public function noOpWhenModuleDisabled(): void
    {
        $this->helper->method('isEnabled')->willReturn(false);
        $this->collectionFactory->expects($this->never())->method('create');
        $this->segmentManagement->expects($this->never())->method('refreshSegment');

        $this->cron->execute();
    }

    #[Test]
    public function returnsEarlyWhenNoCronSegments(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->collectionOf([]);
        $this->segmentManagement->expects($this->never())->method('refreshSegment');

        $this->cron->execute();
    }

    #[Test]
    public function refreshesSegmentWithEmptyExpressionEveryRun(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->collectionOf([$this->segment('', '2027-01-15 07:59:00', 2)]);

        $this->segmentManagement->expects($this->once())
            ->method('refreshSegment')->with(2)->willReturn(4);
        $this->logResource->expects($this->once())
            ->method('log')->with(2, 'refresh', $this->stringContains('4 customers'));

        $this->cron->execute();
    }

    #[Test]
    public function refreshesSegmentThatHasNeverBeenRefreshed(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->stubScheduleMatches();
        // Empty last_refreshed => never refreshed => due regardless of expression.
        $this->collectionOf([$this->segment('0 3 * * *', '', 3)]);

        $this->segmentManagement->expects($this->once())
            ->method('refreshSegment')->with(3)->willReturn(1);

        $this->cron->execute();
    }

    #[Test]
    public function refreshesSegmentWhenMostRecentOccurrenceIsAfterLastRefresh(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->stubScheduleMatches();
        // last_refreshed is in the distant past, so the most-recent occurrence (≈now) is newer.
        $this->collectionOf([$this->segment('*/5 * * * *', '2000-01-01 00:00:00', 5)]);

        $this->segmentManagement->expects($this->once())
            ->method('refreshSegment')->with(5)->willReturn(9);
        $this->logResource->expects($this->once())->method('log');

        $this->cron->execute();
    }

    #[Test]
    public function skipsSegmentNotYetDue(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->stubScheduleMatches();
        // last_refreshed is AFTER the current mocked now, so no occurrence is newer => not due.
        $this->collectionOf([$this->segment('*/5 * * * *', '2030-01-01 00:00:00', 6)]);

        $this->segmentManagement->expects($this->never())->method('refreshSegment');
        $this->logResource->expects($this->never())->method('log');

        $this->cron->execute();
    }

    #[Test]
    public function skipsSegmentWithUnparseableExpression(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        // trySchedule throws on the malformed expression => most-recent time is null
        // => segment is skipped, not run every tick.
        $schedule = $this->getMockBuilder(Schedule::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['trySchedule'])
            ->getMock();
        $schedule->method('trySchedule')->willThrowException(new \Exception('bad'));
        $this->scheduleFactory->method('create')->willReturn($schedule);
        $this->collectionOf([$this->segment('not-a-cron', '2020-01-01 00:00:00', 7)]);

        $this->segmentManagement->expects($this->never())->method('refreshSegment');

        $this->cron->execute();
    }
}
