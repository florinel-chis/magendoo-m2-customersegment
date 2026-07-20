<?php
/**
 * Magendoo CustomerSegment - segment management service contract
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Api;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Orchestrates segment membership: refresh, per-customer realtime re-evaluation and customer export.
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
     * Re-evaluate a single customer against all active realtime segments and sync membership.
     *
     * Assigns the customer to every active realtime segment they match and removes them from the
     * ones they no longer match. Does NOT rescan the whole customer base.
     *
     * @param int $customerId
     * @return void
     */
    public function updateCustomerMembership(int $customerId): void;

    /**
     * Export segment customers
     *
     * @param int $segmentId
     * @param string $format csv|xml
     * @return string File content
     * @throws NoSuchEntityException
     * @throws LocalizedException When an unsupported format is requested
     */
    public function exportSegmentCustomers(int $segmentId, string $format): string;
}
