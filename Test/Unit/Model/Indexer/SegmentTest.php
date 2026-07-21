<?php
/**
 * Magendoo CustomerSegment - Segment indexer unit tests
 *
 * Every id the indexer handles is a SEGMENT id (the mview changelog subscribes
 * only the segment table). These tests pin that segment-scoped contract.
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model\Indexer;

use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magendoo\CustomerSegment\Model\Indexer\Segment;
use Magento\Framework\Api\SearchCriteriaBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(Segment::class)]
class SegmentTest extends TestCase
{
    /** @var SegmentManagementInterface&MockObject */
    private SegmentManagementInterface $segmentManagement;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var Segment */
    private Segment $indexer;

    protected function setUp(): void
    {
        $this->segmentManagement = $this->createMock(SegmentManagementInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->indexer = new Segment(
            $this->segmentManagement,
            $this->createMock(SegmentRepositoryInterface::class),
            $this->createMock(SearchCriteriaBuilder::class),
            $this->logger
        );
    }

    #[Test]
    public function executeFullRefreshesAllSegments(): void
    {
        $this->segmentManagement->expects($this->once())->method('refreshAllSegments');
        $this->indexer->executeFull();
    }

    #[Test]
    public function executeFullRethrowsOnFailure(): void
    {
        $this->segmentManagement->method('refreshAllSegments')
            ->willThrowException(new \RuntimeException('fail'));
        $this->logger->expects($this->once())->method('error');

        $this->expectException(\RuntimeException::class);
        $this->indexer->executeFull();
    }

    #[Test]
    public function executeListTreatsEachIdAsASegmentId(): void
    {
        $refreshed = [];
        $this->segmentManagement->method('refreshSegment')
            ->willReturnCallback(function (int $id) use (&$refreshed) {
                $refreshed[] = $id;
                return 0;
            });

        $this->indexer->executeList(['4', '5', '6']);

        $this->assertSame([4, 5, 6], $refreshed);
    }

    #[Test]
    public function executeListSkipsNonPositiveIds(): void
    {
        $refreshed = [];
        $this->segmentManagement->method('refreshSegment')
            ->willReturnCallback(function (int $id) use (&$refreshed) {
                $refreshed[] = $id;
                return 0;
            });

        $this->indexer->executeList(['0', '-1', '7']);

        $this->assertSame([7], $refreshed);
    }

    #[Test]
    public function executeListContinuesAfterOneSegmentFails(): void
    {
        $refreshed = [];
        $this->segmentManagement->method('refreshSegment')
            ->willReturnCallback(function (int $id) use (&$refreshed) {
                if ($id === 2) {
                    throw new \RuntimeException('bad segment');
                }
                $refreshed[] = $id;
                return 0;
            });
        $this->logger->expects($this->once())->method('error');

        $this->indexer->executeList([1, 2, 3]);

        // Segment 2 failed but 1 and 3 still refreshed — no abort.
        $this->assertSame([1, 3], $refreshed);
    }

    #[Test]
    public function executeRowRefreshesSingleSegment(): void
    {
        $this->segmentManagement->expects($this->once())
            ->method('refreshSegment')->with(15)->willReturn(0);

        $this->indexer->executeRow(15);
    }

    #[Test]
    public function executeRowIgnoresNonPositiveId(): void
    {
        $this->segmentManagement->expects($this->never())->method('refreshSegment');
        $this->indexer->executeRow(0);
    }

    #[Test]
    public function executeRowSwallowsFailure(): void
    {
        $this->segmentManagement->method('refreshSegment')
            ->willThrowException(new \RuntimeException('x'));
        $this->logger->expects($this->once())->method('error');

        // Must not bubble up out of a changelog row.
        $this->indexer->executeRow(9);
    }

    #[Test]
    public function executeDelegatesToListOfSegmentIds(): void
    {
        $refreshed = [];
        $this->segmentManagement->method('refreshSegment')
            ->willReturnCallback(function (int $id) use (&$refreshed) {
                $refreshed[] = $id;
                return 0;
            });

        $this->indexer->execute([20, 21]);

        $this->assertSame([20, 21], $refreshed);
    }
}
