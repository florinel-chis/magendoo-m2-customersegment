<?php
/**
 * Magendoo CustomerSegment - Realtime membership on customer registration
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
 * Re-evaluates realtime segment membership for a newly registered customer.
 */
class CustomerRegister implements ObserverInterface
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

        $customer = $observer->getEvent()->getCustomer();

        if (!$customer || !$customer->getId()) {
            return;
        }

        try {
            $this->segmentManagement->updateCustomerMembership((int) $customer->getId());
        } catch (\Exception $e) {
            $this->logger->error('CustomerRegister observer error: ' . $e->getMessage());
        }
    }
}
