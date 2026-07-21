<?php
/**
 * Magendoo CustomerSegment - LogSegmentSave observer unit tests
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Observer;

use Magendoo\CustomerSegment\Model\ResourceModel\Log as LogResource;
use Magendoo\CustomerSegment\Model\Segment;
use Magendoo\CustomerSegment\Observer\LogSegmentSave;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(LogSegmentSave::class)]
class LogSegmentSaveTest extends TestCase
{
    /** @var LogResource&MockObject */
    private LogResource $logResource;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var LogSegmentSave */
    private LogSegmentSave $observer;

    protected function setUp(): void
    {
        $this->logResource = $this->createMock(LogResource::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->observer = new LogSegmentSave($this->logResource, $this->logger);
    }

    /**
     * @param Segment $segment
     * @return Observer
     */
    private function observerFor(Segment $segment): Observer
    {
        $observer = new Observer();
        $observer->setEvent(new Event(['segment' => $segment]));

        return $observer;
    }

    /**
     * @param array $methods
     * @return Segment&MockObject
     */
    private function segment(array $methods): Segment
    {
        return $this->getMockBuilder(Segment::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }

    #[Test]
    public function logsCreatedActionForNewSegment(): void
    {
        $segment = $this->segment(['getId', 'isObjectNew', 'getName', 'getIsActive']);
        $segment->method('getId')->willReturn(4);
        $segment->method('isObjectNew')->willReturn(true);
        $segment->method('getName')->willReturn('VIPs');
        $segment->method('getIsActive')->willReturn(true);

        $this->logResource->expects($this->once())->method('log')
            ->with(4, 'created', 'Segment "VIPs" created (active: yes)');

        $this->observer->execute($this->observerFor($segment));
    }

    #[Test]
    public function logsUpdatedActionForExistingSegment(): void
    {
        $segment = $this->segment(['getId', 'isObjectNew', 'getName', 'getIsActive']);
        $segment->method('getId')->willReturn(4);
        $segment->method('isObjectNew')->willReturn(false);
        $segment->method('getName')->willReturn('Lapsed');
        $segment->method('getIsActive')->willReturn(false);

        $this->logResource->expects($this->once())->method('log')
            ->with(4, 'updated', 'Segment "Lapsed" updated (active: no)');

        $this->observer->execute($this->observerFor($segment));
    }

    #[Test]
    public function skipsWhenNoSegmentId(): void
    {
        $segment = $this->segment(['getId']);
        $segment->method('getId')->willReturn(null);
        $this->logResource->expects($this->never())->method('log');

        $this->observer->execute($this->observerFor($segment));
    }

    #[Test]
    public function swallowsResourceException(): void
    {
        $segment = $this->segment(['getId', 'isObjectNew', 'getName', 'getIsActive']);
        $segment->method('getId')->willReturn(4);
        $segment->method('isObjectNew')->willReturn(true);
        $segment->method('getName')->willReturn('x');
        $segment->method('getIsActive')->willReturn(true);
        $this->logResource->method('log')->willThrowException(new \RuntimeException('db'));
        $this->logger->expects($this->once())->method('error');

        // Must not throw.
        $this->observer->execute($this->observerFor($segment));
    }
}
