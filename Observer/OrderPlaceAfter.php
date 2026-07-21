<?php
/**
 * Magendoo CustomerSegment - Realtime membership on order placement
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Observer;

use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Helper\Data as Helper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Re-evaluates realtime segment membership for the ordering customer only.
 *
 * A single-customer re-evaluation is used instead of a full segment rescan so
 * that placing an order never triggers an O(customers x conditions) refresh
 * inside the checkout request.
 */
class OrderPlaceAfter implements ObserverInterface
{
    /**
     * @var SegmentManagementInterface
     */
    private SegmentManagementInterface $segmentManagement;

    /**
     * @var Helper
     */
    private Helper $helper;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param SegmentManagementInterface $segmentManagement
     * @param Helper $helper
     * @param LoggerInterface $logger
     */
    public function __construct(
        SegmentManagementInterface $segmentManagement,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->segmentManagement = $segmentManagement;
        $this->helper = $helper;
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
        if (!$this->helper->isEnabled()) {
            return;
        }

        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        $customerId = $order->getCustomerId();

        if (!$customerId) {
            return; // Guest order
        }

        try {
            $this->segmentManagement->updateCustomerMembership((int) $customerId);
        } catch (\Exception $e) {
            $this->logger->error('OrderPlaceAfter observer error: ' . $e->getMessage());
        }
    }
}
