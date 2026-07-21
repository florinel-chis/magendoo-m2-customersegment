<?php
/**
 * Magendoo CustomerSegment - Activity log on segment save
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Observer;

use Magendoo\CustomerSegment\Model\ResourceModel\Log as LogResource;
use Magendoo\CustomerSegment\Model\Segment;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Writes an activity-log row whenever a segment is created or updated.
 */
class LogSegmentSave implements ObserverInterface
{
    /**
     * @var LogResource
     */
    private LogResource $logResource;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param LogResource $logResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        LogResource $logResource,
        LoggerInterface $logger
    ) {
        $this->logResource = $logResource;
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
        /** @var Segment|null $segment */
        $segment = $observer->getEvent()->getSegment();

        if (!$segment || !$segment->getId()) {
            return;
        }

        $action = $segment->isObjectNew() ? 'created' : 'updated';
        $details = sprintf(
            'Segment "%s" %s (active: %s)',
            (string) $segment->getName(),
            $action,
            $segment->getIsActive() ? 'yes' : 'no'
        );

        try {
            $this->logResource->log((int) $segment->getId(), $action, $details);
        } catch (\Exception $e) {
            $this->logger->error('LogSegmentSave observer error: ' . $e->getMessage());
        }
    }
}
