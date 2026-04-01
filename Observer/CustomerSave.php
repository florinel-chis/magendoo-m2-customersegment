<?php
/**
 * Magendoo CustomerSegment Customer Save Observer
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
 * Observer for customer save
 */
class CustomerSave implements ObserverInterface
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
        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $observer->getEvent()->getCustomer();
        
        if (!$customer || !$customer->getId()) {
            return;
        }

        try {
            // For realtime segments, update assignment
            $segments = $this->segmentCollectionFactory->create()
                ->addActiveFilter()
                ->addRefreshModeFilter('realtime')
                ->getItems();

            foreach ($segments as $segment) {
                $matches = $this->segmentManagement->doesCustomerMatchSegment(
                    (int) $customer->getId(),
                    (int) $segment->getId()
                );

                $isInSegment = $this->segmentManagement->isCustomerInSegment(
                    (int) $customer->getId(),
                    (int) $segment->getId()
                );

                // Add or remove based on match
                if ($matches && !$isInSegment) {
                    $this->segmentManagement->assignCustomerToSegment(
                        (int) $customer->getId(),
                        (int) $segment->getId()
                    );
                } elseif (!$matches && $isInSegment) {
                    $this->segmentManagement->removeCustomerFromSegment(
                        (int) $customer->getId(),
                        (int) $segment->getId()
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('CustomerSave observer error: ' . $e->getMessage());
        }
    }
}
