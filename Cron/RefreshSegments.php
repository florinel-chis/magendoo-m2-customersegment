<?php
/**
 * Magendoo CustomerSegment - Scheduled segment refresh
 *
 * Runs on the module's cron group (default every 5 minutes). For each active
 * segment whose refresh_mode is 'cron' it evaluates the segment's individual
 * cron_expression against a since-last-run window: the segment is refreshed
 * when its expression had a scheduled occurrence at any point between its
 * last_refreshed timestamp and now. This keeps per-segment schedules honest
 * even though the dispatcher itself only ticks on its group boundary.
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Cron;

use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Helper\Data as Helper;
use Magendoo\CustomerSegment\Model\ResourceModel\Log as LogResource;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment\CollectionFactory;
use Magento\Cron\Model\Schedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

/**
 * Cron job to refresh customer segments using per-segment scheduling.
 */
class RefreshSegments
{
    /**
     * Upper bound (in minutes) for the backward scan that locates the most
     * recent scheduled occurrence of a segment's cron expression. 31 days
     * covers sub-monthly expressions; the scan breaks early on the first match.
     */
    private const LOOKBACK_MINUTES = 44640;

    /**
     * @var SegmentManagementInterface
     */
    private SegmentManagementInterface $segmentManagement;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var ScheduleFactory
     */
    private ScheduleFactory $scheduleFactory;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var Helper
     */
    private Helper $helper;

    /**
     * @var LogResource
     */
    private LogResource $logResource;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param SegmentManagementInterface $segmentManagement
     * @param CollectionFactory $collectionFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DateTime $dateTime
     * @param Helper $helper
     * @param LogResource $logResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        SegmentManagementInterface $segmentManagement,
        CollectionFactory $collectionFactory,
        ScheduleFactory $scheduleFactory,
        DateTime $dateTime,
        Helper $helper,
        LogResource $logResource,
        LoggerInterface $logger
    ) {
        $this->segmentManagement = $segmentManagement;
        $this->collectionFactory = $collectionFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->dateTime = $dateTime;
        $this->helper = $helper;
        $this->logResource = $logResource;
        $this->logger = $logger;
    }

    /**
     * Execute cron job.
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->helper->isEnabled()) {
            return;
        }

        $this->logger->info('Magendoo CustomerSegment: Starting scheduled segment refresh');

        try {
            $collection = $this->collectionFactory->create();
            $collection->addActiveFilter();
            $collection->addRefreshModeFilter('cron');

            if ($collection->getSize() === 0) {
                $this->logger->info('Magendoo CustomerSegment: No cron segments to refresh');
                return;
            }

            $nowTs = $this->dateTime->gmtTimestamp();
            $refreshed = 0;
            $skipped = 0;

            foreach ($collection as $segment) {
                if (!$this->isSegmentDue($segment, $nowTs)) {
                    $skipped++;
                    continue;
                }

                $this->refreshSegment($segment);
                $refreshed++;
            }

            $this->logger->info(sprintf(
                'Magendoo CustomerSegment: Completed — %d refreshed, %d skipped (not due)',
                $refreshed,
                $skipped
            ));
        } catch (\Exception $e) {
            $this->logger->error('Magendoo CustomerSegment: Cron error: ' . $e->getMessage());
        }
    }

    /**
     * Refresh a single segment and record an activity-log row.
     *
     * @param \Magendoo\CustomerSegment\Model\Segment $segment
     * @return void
     */
    private function refreshSegment($segment): void
    {
        $segmentId = (int) $segment->getId();

        try {
            $count = $this->segmentManagement->refreshSegment($segmentId);
            $this->logger->info(sprintf(
                'Magendoo CustomerSegment: Refreshed "%s" (ID %d) — %d customers',
                (string) $segment->getName(),
                $segmentId,
                $count
            ));
            $this->logResource->log(
                $segmentId,
                'refresh',
                sprintf('Refreshed via cron — %d customers', $count)
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Magendoo CustomerSegment: Error refreshing segment ' . $segmentId . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * Determine whether a segment is due, using a since-last-run window.
     *
     * A segment is due when it has never been refreshed, when it has no
     * per-segment expression (refresh every run), or when its expression's
     * most-recent scheduled occurrence is later than its last_refreshed time.
     *
     * @param \Magendoo\CustomerSegment\Model\Segment $segment
     * @param int $nowTs GMT epoch seconds
     * @return bool
     */
    private function isSegmentDue($segment, int $nowTs): bool
    {
        $cronExpr = trim((string) $segment->getData('cron_expression'));
        if ($cronExpr === '') {
            return true;
        }

        $lastRefreshed = (string) $segment->getData('last_refreshed');
        $lastRefreshedTs = $this->toUtcTimestamp($lastRefreshed);
        if ($lastRefreshedTs === null) {
            return true;
        }

        $mostRecentTs = $this->getMostRecentScheduledTime($cronExpr, $nowTs);
        if ($mostRecentTs === null) {
            // Expression could not be matched within the lookback window;
            // do not refresh to avoid running an unparseable schedule every tick.
            return false;
        }

        return $mostRecentTs > $lastRefreshedTs;
    }

    /**
     * Find the most recent scheduled minute at or before a reference time.
     *
     * Scans backward from $nowTs up to LOOKBACK_MINUTES looking for a minute
     * whose time matches the given cron expression.
     *
     * @param string $cronExpr
     * @param int $nowTs GMT epoch seconds
     * @return int|null Epoch seconds of the matching minute, or null if none
     */
    private function getMostRecentScheduledTime(string $cronExpr, int $nowTs): ?int
    {
        try {
            /** @var Schedule $schedule */
            $schedule = $this->scheduleFactory->create();
            $schedule->setCronExpr($cronExpr);
        } catch (\Exception $e) {
            $this->logger->warning(
                'Magendoo CustomerSegment: Invalid cron expression "' . $cronExpr . '": ' . $e->getMessage()
            );
            return null;
        }

        for ($i = 0; $i <= self::LOOKBACK_MINUTES; $i++) {
            $candidateTs = $nowTs - ($i * 60);
            $schedule->setScheduledAt($this->dateTime->gmtDate('Y-m-d H:i:00', $candidateTs));

            try {
                if ($schedule->trySchedule()) {
                    return $candidateTs - ($candidateTs % 60);
                }
            } catch (\Exception $e) {
                $this->logger->warning(
                    'Magendoo CustomerSegment: Invalid cron expression "' . $cronExpr . '": ' . $e->getMessage()
                );
                return null;
            }
        }

        return null;
    }

    /**
     * Parse a stored UTC datetime string into epoch seconds.
     *
     * @param string $value
     * @return int|null
     */
    private function toUtcTimestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->getTimestamp();
        } catch (\Exception $e) {
            return null;
        }
    }
}
