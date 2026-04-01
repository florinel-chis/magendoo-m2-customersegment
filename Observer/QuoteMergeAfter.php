<?php
/**
 * Magendoo CustomerSegment Quote Merge After Observer
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
use Psr\Log\LoggerInterface;

/**
 * Observer for quote merge (login)
 */
class QuoteMergeAfter implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    ) {
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
        // Quote merge event - can be used to update cart-based segments
        // This is a placeholder for cart-related segment updates
        
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        
        if ($quote && $quote->getCustomerId()) {
            $this->logger->debug(
                sprintf('Quote merged for customer %d', $quote->getCustomerId())
            );
        }
    }
}
