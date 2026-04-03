<?php
/**
 * Magendoo CustomerSegment Refresh Segments Cron
 *
 * This cron job runs on the global schedule (configurable in admin, default: every 5 min).
 * For each active cron/realtime segment it checks the segment's individual cron_expression
 * against the current time. Only segments whose expression matches (or who have no expression
 * set) are refreshed on a given run.
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Cron;

use Magento\Cron\Model\Schedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Cron job to refresh customer segments with per-segment scheduling
 */
class RefreshSegments
{
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
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param SegmentManagementInterface $segmentManagement
     * @param CollectionFactory $collectionFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        SegmentManagementInterface $segmentManagement,
        CollectionFactory $collectionFactory,
        ScheduleFactory $scheduleFactory,
        DateTime $dateTime,
        LoggerInterface $logger
    ) {
        $this->segmentManagement = $segmentManagement;
        $this->collectionFactory = $collectionFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    /**
     * Execute cron job.
     *
     * Iterates over active cron/realtime segments and refreshes only
     * those whose per-segment cron_expression matches the current time.
     * Segments without a cron_expression refresh on every run.
     *
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info('Magendoo CustomerSegment: Starting scheduled segment refresh');

        try {
            $collection = $this->collectionFactory->create();
            $collection->addActiveFilter();
            $collection->addFieldToFilter('refresh_mode', ['in' => ['cron', 'realtime']]);

            if ($collection->getSize() === 0) {
                $this->logger->info('Magendoo CustomerSegment: No segments to refresh');
                return;
            }

            $now = $this->dateTime->gmtDate();
            $refreshed = 0;
            $skipped = 0;

            foreach ($collection as $segment) {
                $cronExpr = $segment->getData('cron_expression');

                // If segment has its own cron expression, check if it's due now.
                // If no expression is set, refresh on every run (backward compatible).
                if ($cronExpr && !$this->isExpressionDueNow($cronExpr, $now)) {
                    $skipped++;
                    continue;
                }

                try {
                    $count = $this->segmentManagement->refreshSegment((int) $segment->getId());
                    $this->logger->info(sprintf(
                        'Magendoo CustomerSegment: Refreshed "%s" (ID %d) — %d customers',
                        $segment->getName(),
                        $segment->getId(),
                        $count
                    ));
                    $refreshed++;
                } catch (\Exception $e) {
                    $this->logger->error(
                        'Magendoo CustomerSegment: Error refreshing segment '
                        . $segment->getId() . ': ' . $e->getMessage()
                    );
                }
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
     * Check whether a cron expression matches the current time.
     *
     * Uses Magento's own Schedule model which has full cron expression
     * parsing including ranges, steps, lists, and named months/days.
     *
     * @param string $cronExpr Cron expression, e.g. "0 2 * * *"
     * @param string $now GMT datetime string
     * @return bool
     */
    private function isExpressionDueNow(string $cronExpr, string $now): bool
    {
        try {
            /** @var Schedule $schedule */
            $schedule = $this->scheduleFactory->create();
            $schedule->setCronExpr($cronExpr);
            $schedule->setScheduledAt($now);

            return $schedule->trySchedule();
        } catch (\Exception $e) {
            // Invalid expression — treat as "always due" so the segment
            // still refreshes rather than silently never running.
            $this->logger->warning(
                'Magendoo CustomerSegment: Invalid cron expression "'
                . $cronExpr . '", refreshing anyway: ' . $e->getMessage()
            );
            return true;
        }
    }
}
