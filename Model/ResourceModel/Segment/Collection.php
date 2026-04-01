<?php
/**
 * Magendoo CustomerSegment Segment Collection
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\ResourceModel\Segment;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magendoo\CustomerSegment\Model\Segment;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment as SegmentResource;

/**
 * Customer Segment Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'segment_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'magendoo_customersegment_segment_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'segment_collection';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(Segment::class, SegmentResource::class);
    }

    /**
     * Add active filter
     *
     * @return $this
     */
    public function addActiveFilter(): static
    {
        $this->addFieldToFilter('is_active', 1);
        return $this;
    }

    /**
     * Add refresh mode filter
     *
     * @param string $mode
     * @return $this
     */
    public function addRefreshModeFilter(string $mode): static
    {
        $this->addFieldToFilter('refresh_mode', $mode);
        return $this;
    }

    /**
     * Add customer filter (get segments containing specific customer)
     *
     * @param int $customerId
     * @return $this
     */
    public function addCustomerFilter(int $customerId): static
    {
        $this->getSelect()->join(
            ['seg_cust' => $this->getTable('magendoo_customer_segment_customer')],
            'main_table.segment_id = seg_cust.segment_id',
            []
        )->where('seg_cust.customer_id = ?', $customerId);

        return $this;
    }

    /**
     * Add need refresh filter (for cron processing)
     *
     * @return $this
     */
    public function addNeedsRefreshFilter(): static
    {
        $this->addActiveFilter();
        
        // Filter for segments that need refreshing:
        // - Cron mode: any active
        // - Realtime mode: not refreshed in last hour
        $this->getSelect()->where(
            '(refresh_mode = ?) OR ' .
            '(refresh_mode = ? AND (last_refreshed IS NULL OR last_refreshed < DATE_SUB(NOW(), INTERVAL 1 HOUR)))',
            Segment::REFRESH_MODE_CRON,
            Segment::REFRESH_MODE_REALTIME
        );

        return $this;
    }

    /**
     * Join customer count (if not already joined)
     *
     * @return $this
     */
    public function joinCustomerCount(): static
    {
        // customer_count is already a column in the table
        // This method can be used for additional aggregations if needed
        return $this;
    }
}
