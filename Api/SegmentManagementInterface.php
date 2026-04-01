<?php
/**
 * Magendoo CustomerSegment Segment Management Interface
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Api;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Segment management interface
 *
 * @api
 */
interface SegmentManagementInterface
{
    /**
     * Refresh segment and return matched customer count
     *
     * @param int $segmentId
     * @return int Number of matched customers
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     */
    public function refreshSegment(int $segmentId): int;

    /**
     * Refresh all active segments
     *
     * @return void
     */
    public function refreshAllSegments(): void;

    /**
     * Get customer segment IDs
     *
     * @param int $customerId
     * @return int[]
     */
    public function getCustomerSegmentIds(int $customerId): array;

    /**
     * Get customer segments data
     *
     * @param int $customerId
     * @return array Array of segment data
     */
    public function getCustomerSegments(int $customerId): array;

    /**
     * Assign customer to segment
     *
     * @param int $customerId
     * @param int $segmentId
     * @return bool
     * @throws CouldNotSaveException
     */
    public function assignCustomerToSegment(int $customerId, int $segmentId): bool;

    /**
     * Remove customer from segment
     *
     * @param int $customerId
     * @param int $segmentId
     * @return bool
     */
    public function removeCustomerFromSegment(int $customerId, int $segmentId): bool;

    /**
     * Check if customer is in segment
     *
     * @param int $customerId
     * @param int $segmentId
     * @return bool
     */
    public function isCustomerInSegment(int $customerId, int $segmentId): bool;

    /**
     * Check if customer matches segment conditions
     *
     * @param int $customerId
     * @param int $segmentId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function doesCustomerMatchSegment(int $customerId, int $segmentId): bool;

    /**
     * Mass refresh segments by IDs
     *
     * @param int[] $segmentIds
     * @return int Total customers affected
     */
    public function massRefresh(array $segmentIds): int;

    /**
     * Export segment customers
     *
     * @param int $segmentId
     * @param string $format csv|xml
     * @return string File content
     * @throws NoSuchEntityException
     */
    public function exportSegmentCustomers(int $segmentId, string $format): string;
}
