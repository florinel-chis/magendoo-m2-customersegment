<?php
/**
 * Magendoo CustomerSegment Customer Login Observer
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
use Psr\Log\LoggerInterface;

/**
 * Observer for customer login
 */
class CustomerLogin implements ObserverInterface
{
    /**
     * @var SegmentManagementInterface
     */
    protected SegmentManagementInterface $segmentManagement;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @param SegmentManagementInterface $segmentManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        SegmentManagementInterface $segmentManagement,
        LoggerInterface $logger
    ) {
        $this->segmentManagement = $segmentManagement;
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
        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $observer->getEvent()->getCustomer();
        
        if (!$customer || !$customer->getId()) {
            return;
        }

        try {
            // Get customer's segments for session storage or caching
            $segments = $this->segmentManagement->getCustomerSegments((int) $customer->getId());
            
            // Store in customer session or cache for quick access
            // This is a placeholder - actual implementation depends on requirements
            $this->logger->debug(
                sprintf('Customer %d logged in with %d segments', 
                    $customer->getId(), 
                    count($segments)
                )
            );
        } catch (\Exception $e) {
            $this->logger->error('CustomerLogin observer error: ' . $e->getMessage());
        }
    }
}
