<?php
/**
 * Magendoo CustomerSegment Segment Interface
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Customer Segment Interface
 *
 * @api
 */
interface SegmentInterface extends ExtensibleDataInterface
{
    /** Constants for field names */
    public const SEGMENT_ID = 'segment_id';
    public const NAME = 'name';
    public const DESCRIPTION = 'description';
    public const IS_ACTIVE = 'is_active';
    public const CONDITIONS_SERIALIZED = 'conditions_serialized';
    public const REFRESH_MODE = 'refresh_mode';
    public const CRON_EXPRESSION = 'cron_expression';
    public const CUSTOMER_COUNT = 'customer_count';
    public const LAST_REFRESHED = 'last_refreshed';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /** Refresh modes */
    public const REFRESH_MODE_MANUAL = 'manual';
    public const REFRESH_MODE_CRON = 'cron';
    public const REFRESH_MODE_REALTIME = 'realtime';

    /**
     * Get segment ID
     *
     * @return int|null
     */
    public function getSegmentId(): ?int;

    /**
     * Set segment ID
     *
     * @param int $segmentId
     * @return $this
     */
    public function setSegmentId(int $segmentId): static;

    /**
     * Get segment name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Set segment name
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): static;

    /**
     * Get description
     *
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * Set description
     *
     * @param string|null $description
     * @return $this
     */
    public function setDescription(?string $description): static;

    /**
     * Get is active
     *
     * @return bool
     */
    public function getIsActive(): bool;

    /**
     * Set is active
     *
     * @param bool $isActive
     * @return $this
     */
    public function setIsActive(bool $isActive): static;

    /**
     * Get serialized conditions
     *
     * @return string|null
     */
    public function getConditionsSerialized(): ?string;

    /**
     * Set serialized conditions
     *
     * @param string|null $conditionsSerialized
     * @return $this
     */
    public function setConditionsSerialized(?string $conditionsSerialized): static;

    /**
     * Get refresh mode
     *
     * @return string
     */
    public function getRefreshMode(): string;

    /**
     * Set refresh mode
     *
     * @param string $refreshMode
     * @return $this
     */
    public function setRefreshMode(string $refreshMode): static;

    /**
     * Get cron expression
     *
     * @return string|null
     */
    public function getCronExpression(): ?string;

    /**
     * Set cron expression
     *
     * @param string|null $cronExpression
     * @return $this
     */
    public function setCronExpression(?string $cronExpression): static;

    /**
     * Get customer count
     *
     * @return int
     */
    public function getCustomerCount(): int;

    /**
     * Set customer count
     *
     * @param int $customerCount
     * @return $this
     */
    public function setCustomerCount(int $customerCount): static;

    /**
     * Get last refreshed timestamp
     *
     * @return string|null
     */
    public function getLastRefreshed(): ?string;

    /**
     * Set last refreshed timestamp
     *
     * @param string|null $lastRefreshed
     * @return $this
     */
    public function setLastRefreshed(?string $lastRefreshed): static;

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set created at
     *
     * @param string|null $createdAt
     * @return $this
     */
    public function setCreatedAt(?string $createdAt): static;

    /**
     * Get updated at
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Set updated at
     *
     * @param string|null $updatedAt
     * @return $this
     */
    public function setUpdatedAt(?string $updatedAt): static;

    /**
     * Get extension attributes
     *
     * @return \Magendoo\CustomerSegment\Api\Data\SegmentExtensionInterface|null
     */
    public function getExtensionAttributes(): ?\Magendoo\CustomerSegment\Api\Data\SegmentExtensionInterface;

    /**
     * Set extension attributes
     *
     * @param \Magendoo\CustomerSegment\Api\Data\SegmentExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(\Magendoo\CustomerSegment\Api\Data\SegmentExtensionInterface $extensionAttributes): static;
}
