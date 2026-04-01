<?php
/**
 * Magendoo CustomerSegment Refresh Segments Cron
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Cron;

use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Cron job to refresh customer segments
 */
class RefreshSegments
{
    /**
     * @var SegmentManagementInterface
     */
    protected SegmentManagementInterface $segmentManagement;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $collectionFactory;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @param SegmentManagementInterface $segmentManagement
     * @param CollectionFactory $collectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        SegmentManagementInterface $segmentManagement,
        CollectionFactory $collectionFactory,
        LoggerInterface $logger
    ) {
        $this->segmentManagement = $segmentManagement;
        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info('Magendoo CustomerSegment: Starting scheduled segment refresh');

        try {
            // Get segments that need refreshing
            $collection = $this->collectionFactory->create();
            $collection->addActiveFilter();
            $collection->addFieldToFilter('refresh_mode', ['in' => ['cron', 'realtime']]);

            $segmentIds = $collection->getColumnValues('segment_id');

            if (empty($segmentIds)) {
                $this->logger->info('Magendoo CustomerSegment: No segments to refresh');
                return;
            }

            $this->logger->info(sprintf('Magendoo CustomerSegment: Refreshing %d segments', count($segmentIds)));

            $totalCustomers = $this->segmentManagement->massRefresh($segmentIds);

            $this->logger->info(sprintf('Magendoo CustomerSegment: Refreshed %d total customers', $totalCustomers));

        } catch (\Exception $e) {
            $this->logger->error('Magendoo CustomerSegment: Error during refresh: ' . $e->getMessage());
        }
    }
}
