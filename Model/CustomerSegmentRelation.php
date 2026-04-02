<?php
/**
 * Magendoo CustomerSegment Customer Segment Relation Model
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model;

use Magento\Framework\Model\AbstractModel;

class CustomerSegmentRelation extends AbstractModel
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Customer::class);
    }

    /**
     * Get segment ID
     *
     * @return int
     */
    public function getSegmentId(): int
    {
        return (int) $this->getData('segment_id');
    }

    /**
     * Set segment ID
     *
     * @param int $segmentId
     * @return $this
     */
    public function setSegmentId(int $segmentId): static
    {
        return $this->setData('segment_id', $segmentId);
    }

    /**
     * Get customer ID
     *
     * @return int
     */
    public function getCustomerId(): int
    {
        return (int) $this->getData('customer_id');
    }

    /**
     * Set customer ID
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): static
    {
        return $this->setData('customer_id', $customerId);
    }

    /**
     * Get assigned at
     *
     * @return string|null
     */
    public function getAssignedAt(): ?string
    {
        return $this->getData('assigned_at');
    }

    /**
     * Set assigned at
     *
     * @param string $assignedAt
     * @return $this
     */
    public function setAssignedAt(string $assignedAt): static
    {
        return $this->setData('assigned_at', $assignedAt);
    }
}
