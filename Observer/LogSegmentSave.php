<?php
/**
 * Magendoo CustomerSegment Log Segment Save Observer
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
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magendoo\CustomerSegment\Model\Segment;
use Psr\Log\LoggerInterface;

/**
 * Observer to log segment save
 */
class LogSegmentSave implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var DateTime
     */
    protected DateTime $dateTime;

    /**
     * @param LoggerInterface $logger
     * @param DateTime $dateTime
     */
    public function __construct(
        LoggerInterface $logger,
        DateTime $dateTime
    ) {
        $this->logger = $logger;
        $this->dateTime = $dateTime;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Segment $segment */
        $segment = $observer->getEvent()->getSegment();
        
        if (!$segment) {
            return;
        }

        $action = $segment->isObjectNew() ? 'created' : 'updated';
        
        $this->logger->info(
            sprintf('Segment %s: ID=%d, Name=%s', 
                $action,
                $segment->getId(),
                $segment->getName()
            ),
            [
                'segment_id' => $segment->getId(),
                'name' => $segment->getName(),
                'is_active' => $segment->getIsActive(),
                'timestamp' => $this->dateTime->gmtDate()
            ]
        );
    }
}
