<?php
/**
 * Magendoo CustomerSegment Customer Grid Plugin
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Plugin;

use Magento\Customer\Model\ResourceModel\Grid\Collection as CustomerGridCollection;
use Magendoo\CustomerSegment\Api\SegmentManagementInterface;

/**
 * Plugin to add segment data to customer grid
 */
class CustomerGridPlugin
{
    /**
     * @var SegmentManagementInterface
     */
    protected SegmentManagementInterface $segmentManagement;

    /**
     * @param SegmentManagementInterface $segmentManagement
     */
    public function __construct(
        SegmentManagementInterface $segmentManagement
    ) {
        $this->segmentManagement = $segmentManagement;
    }

    /**
     * Add segment column to customer grid collection
     *
     * @param CustomerGridCollection $subject
     * @param bool $printQuery
     * @param bool $logQuery
     * @return array
     */
    public function beforeLoad(CustomerGridCollection $subject, $printQuery = false, $logQuery = false): array
    {
        if (!$subject->isLoaded()) {
            // Add segment column to select
            // This is a simplified version - actual implementation would require
            // joining with the segment relationship table
        }

        return [$printQuery, $logQuery];
    }
}
