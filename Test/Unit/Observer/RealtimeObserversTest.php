<?php
/**
 * Magendoo CustomerSegment - Realtime membership observer unit tests
 *
 * The register/login/save/order/quote-merge observers all follow the same
 * contract (C3/C4): honour the enable gate, key on the real customer id from the
 * event, call ONLY updateCustomerMembership, and never let an exception escape.
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Observer;

use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Helper\Data as Helper;
use Magendoo\CustomerSegment\Observer\CustomerLogin;
use Magendoo\CustomerSegment\Observer\CustomerRegister;
use Magendoo\CustomerSegment\Observer\CustomerSave;
use Magendoo\CustomerSegment\Observer\OrderPlaceAfter;
use Magendoo\CustomerSegment\Observer\QuoteMergeAfter;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CustomerRegister::class)]
#[CoversClass(CustomerLogin::class)]
#[CoversClass(CustomerSave::class)]
#[CoversClass(OrderPlaceAfter::class)]
#[CoversClass(QuoteMergeAfter::class)]
class RealtimeObserversTest extends TestCase
{
    /** @var SegmentManagementInterface&MockObject */
    private SegmentManagementInterface $segmentManagement;

    /** @var Helper&MockObject */
    private Helper $helper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->segmentManagement = $this->createMock(SegmentManagementInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * Build an observer for the given class using the shared 3-arg constructor.
     *
     * @param class-string $class
     * @return ObserverInterface
     */
    private function makeObserver(string $class): ObserverInterface
    {
        return new $class($this->segmentManagement, $this->helper, $this->logger);
    }

    /**
     * Build an Observer carrying an event exposing the given magic getters.
     *
     * @param array<string, mixed> $eventData
     * @return Observer
     */
    private function makeEvent(array $eventData): Observer
    {
        $event = new Event($eventData);
        $observer = new Observer();
        $observer->setEvent($event);

        return $observer;
    }

    /**
     * A DataObject-backed entity whose getId()/getCustomerId() behave like the real event payloads.
     *
     * @param int|null $id
     * @return DataObject
     */
    private function entity(?int $id): DataObject
    {
        return new DataObject(['id' => $id, 'customer_id' => $id]);
    }

    public static function customerEventObservers(): array
    {
        return [
            'register' => [CustomerRegister::class, 'customer'],
            'login' => [CustomerLogin::class, 'customer'],
            'save' => [CustomerSave::class, 'customer'],
        ];
    }

    #[Test]
    #[DataProvider('customerEventObservers')]
    public function customerObserverUpdatesMembershipForEventCustomer(string $class, string $key): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->segmentManagement->expects($this->once())
            ->method('updateCustomerMembership')->with(77);

        $observer = $this->makeObserver($class);
        $observer->execute($this->makeEvent([$key => $this->entity(77)]));
    }

    #[Test]
    #[DataProvider('customerEventObservers')]
    public function customerObserverNoOpWhenDisabled(string $class, string $key): void
    {
        $this->helper->method('isEnabled')->willReturn(false);
        $this->segmentManagement->expects($this->never())->method('updateCustomerMembership');

        $observer = $this->makeObserver($class);
        $observer->execute($this->makeEvent([$key => $this->entity(77)]));
    }

    #[Test]
    #[DataProvider('customerEventObservers')]
    public function customerObserverSkipsMissingCustomer(string $class, string $key): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->segmentManagement->expects($this->never())->method('updateCustomerMembership');

        $observer = $this->makeObserver($class);
        $observer->execute($this->makeEvent([$key => null]));
    }

    #[Test]
    #[DataProvider('customerEventObservers')]
    public function customerObserverSwallowsServiceException(string $class, string $key): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->segmentManagement->method('updateCustomerMembership')
            ->willThrowException(new \RuntimeException('svc down'));
        $this->logger->expects($this->once())->method('error');

        $observer = $this->makeObserver($class);
        // Must not throw.
        $observer->execute($this->makeEvent([$key => $this->entity(5)]));
    }

    #[Test]
    public function orderObserverUpdatesMembershipForOrderingCustomer(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->segmentManagement->expects($this->once())
            ->method('updateCustomerMembership')->with(88);

        $observer = $this->makeObserver(OrderPlaceAfter::class);
        $observer->execute($this->makeEvent(['order' => new DataObject(['customer_id' => 88])]));
    }

    #[Test]
    public function orderObserverSkipsGuestOrder(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->segmentManagement->expects($this->never())->method('updateCustomerMembership');

        $observer = $this->makeObserver(OrderPlaceAfter::class);
        $observer->execute($this->makeEvent(['order' => new DataObject(['customer_id' => null])]));
    }

    #[Test]
    public function orderObserverNoOpWhenDisabled(): void
    {
        $this->helper->method('isEnabled')->willReturn(false);
        $this->segmentManagement->expects($this->never())->method('updateCustomerMembership');

        $observer = $this->makeObserver(OrderPlaceAfter::class);
        $observer->execute($this->makeEvent(['order' => new DataObject(['customer_id' => 88])]));
    }

    #[Test]
    public function quoteMergeObserverUpdatesMembershipForQuoteCustomer(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->segmentManagement->expects($this->once())
            ->method('updateCustomerMembership')->with(99);

        $observer = $this->makeObserver(QuoteMergeAfter::class);
        $observer->execute($this->makeEvent(['quote' => new DataObject(['customer_id' => 99])]));
    }

    #[Test]
    public function quoteMergeObserverSkipsGuestQuote(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->segmentManagement->expects($this->never())->method('updateCustomerMembership');

        $observer = $this->makeObserver(QuoteMergeAfter::class);
        $observer->execute($this->makeEvent(['quote' => new DataObject(['customer_id' => 0])]));
    }
}
