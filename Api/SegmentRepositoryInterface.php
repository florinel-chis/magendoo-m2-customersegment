<?php
/**
 * Magendoo CustomerSegment Segment Repository Interface
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Api;

use Magendoo\CustomerSegment\Api\Data\SegmentInterface;
use Magendoo\CustomerSegment\Api\Data\SegmentSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Segment repository interface
 *
 * @api
 */
interface SegmentRepositoryInterface
{
    /**
     * Save segment
     *
     * @param SegmentInterface $segment
     * @return SegmentInterface
     * @throws CouldNotSaveException
     */
    public function save(SegmentInterface $segment): SegmentInterface;

    /**
     * Get segment by ID
     *
     * @param int $segmentId
     * @return SegmentInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $segmentId): SegmentInterface;

    /**
     * Get segment by ID with store ID (for multi-store compatibility)
     *
     * @param int $segmentId
     * @param int|null $storeId
     * @return SegmentInterface
     * @throws NoSuchEntityException
     */
    public function get(int $segmentId, ?int $storeId = null): SegmentInterface;

    /**
     * Retrieve segments matching the specified criteria
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SegmentSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SegmentSearchResultsInterface;

    /**
     * Delete segment
     *
     * @param SegmentInterface $segment
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(SegmentInterface $segment): bool;

    /**
     * Delete segment by ID
     *
     * @param int $segmentId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $segmentId): bool;
}
