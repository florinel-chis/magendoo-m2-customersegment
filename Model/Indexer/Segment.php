<?php
/**
 * Magendoo CustomerSegment - Segment reindex action
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

/**
 * Segment-scoped indexer.
 *
 * Every id handled here is a SEGMENT id. The mview changelog subscribes only the
 * magendoo_customer_segment table (see etc/mview.xml), so partial reindex ids map
 * one-to-one to segments whose own definition changed. Customer-driven membership
 * changes are handled separately by the realtime observers, not by this indexer.
 */
class Segment implements ActionInterface, MviewActionInterface
{
    /**
     * @var SegmentManagementInterface
     */
    private $segmentManagement;

    /**
     * @var SegmentRepositoryInterface
     */
    private $segmentRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SegmentManagementInterface $segmentManagement
     * @param SegmentRepositoryInterface $segmentRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        SegmentManagementInterface $segmentManagement,
        SegmentRepositoryInterface $segmentRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger
    ) {
        $this->segmentManagement = $segmentManagement;
        $this->segmentRepository = $segmentRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * Execute full indexation
     *
     * @return void
     */
    public function executeFull(): void
    {
        $this->logger->info('Starting full customer segment reindex');

        try {
            $this->segmentManagement->refreshAllSegments();
            $this->logger->info('Full customer segment reindex completed');
        } catch (\Exception $e) {
            $this->logger->error('Error during full segment reindex: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute partial indexation for a list of segment IDs
     *
     * @param int[]|string[] $ids Segment IDs to refresh.
     * @return void
     */
    public function executeList(array $ids): void
    {
        $this->logger->info('Starting partial customer segment reindex for segment IDs: ' . implode(',', $ids));

        foreach ($ids as $id) {
            $segmentId = (int) $id;
            if ($segmentId <= 0) {
                continue;
            }

            try {
                $this->segmentManagement->refreshSegment($segmentId);
            } catch (\Exception $e) {
                $this->logger->error('Error refreshing segment ' . $segmentId . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Execute partial indexation for a single segment ID
     *
     * @param int $id Segment ID to refresh.
     * @return void
     */
    public function executeRow($id): void
    {
        $segmentId = (int) $id;
        if ($segmentId <= 0) {
            return;
        }

        try {
            $this->segmentManagement->refreshSegment($segmentId);
        } catch (\Exception $e) {
            $this->logger->error('Error refreshing segment ' . $segmentId . ': ' . $e->getMessage());
        }
    }

    /**
     * Execute materialization on changelog entities (segment IDs)
     *
     * @param int[] $ids Segment IDs collected from the changelog.
     * @return void
     */
    public function execute($ids): void
    {
        $this->executeList($ids);
    }
}
