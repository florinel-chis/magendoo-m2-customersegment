<?php
/**
 * Magendoo CustomerSegment Order Place After Observer
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Observer for order placement
 */
class OrderPlaceAfter implements ObserverInterface
{
    /**
     * @var SegmentManagementInterface
     */
    protected SegmentManagementInterface $segmentManagement;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $segmentCollectionFactory;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @param SegmentManagementInterface $segmentManagement
     * @param CollectionFactory $segmentCollectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        SegmentManagementInterface $segmentManagement,
        CollectionFactory $segmentCollectionFactory,
        LoggerInterface $logger
    ) {
        $this->segmentManagement = $segmentManagement;
        $this->segmentCollectionFactory = $segmentCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();
        
        $customerId = $order->getCustomerId();
        
        if (!$customerId) {
            return; // Guest order
        }

        try {
            // Get order-related segments (those with order conditions)
            $segments = $this->segmentCollectionFactory->create()
                ->addActiveFilter()
                ->addRefreshModeFilter('realtime')
                ->getItems();

            foreach ($segments as $segment) {
                // Refresh segment to recalculate customer count
                $this->segmentManagement->refreshSegment((int) $segment->getId());
            }
        } catch (\Exception $e) {
            $this->logger->error('OrderPlaceAfter observer error: ' . $e->getMessage());
        }
    }
}
