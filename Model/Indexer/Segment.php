<?php
/**
 * Magendoo CustomerSegment Indexer
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

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
     * Execute partial indexation by ID list
     *
     * @param array $ids
     * @return void
     */
    public function executeList(array $ids): void
    {
        $this->logger->info('Starting partial customer segment reindex for IDs: ' . implode(',', $ids));

        foreach ($ids as $segmentId) {
            try {
                $this->segmentManagement->refreshSegment((int) $segmentId);
            } catch (\Exception $e) {
                $this->logger->error('Error refreshing segment ' . $segmentId . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Execute partial indexation by ID
     *
     * @param int $id
     * @return void
     */
    public function executeRow($id): void
    {
        try {
            $this->segmentManagement->refreshSegment((int) $id);
        } catch (\Exception $e) {
            $this->logger->error('Error refreshing segment ' . $id . ': ' . $e->getMessage());
        }
    }

    /**
     * Execute materialization on changelog entities
     *
     * @param int[] $ids
     * @return void
     */
    public function execute($ids): void
    {
        $this->executeList($ids);
    }
}
