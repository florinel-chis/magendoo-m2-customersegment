<?php
/**
 * Magendoo CustomerSegment - Realtime membership on quote merge
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
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
 * Re-evaluates realtime segment membership when a guest quote is merged into
 * the logged-in customer's quote.
 */
class QuoteMergeAfter implements ObserverInterface
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

        /** @var \Magento\Quote\Model\Quote|null $quote */
        $quote = $observer->getEvent()->getQuote();

        if (!$quote || !$quote->getCustomerId()) {
            return;
        }

        try {
            $this->segmentManagement->updateCustomerMembership((int) $quote->getCustomerId());
        } catch (\Exception $e) {
            $this->logger->error('QuoteMergeAfter observer error: ' . $e->getMessage());
        }
    }
}
