<?php
/**
 * Magendoo CustomerSegment SegmentManagement Test
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Magendoo\CustomerSegment\Api\Data\SegmentInterface;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magendoo\CustomerSegment\Model\Condition\CombineFactory;
use Magendoo\CustomerSegment\Model\Condition\Customer as CustomerCondition;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment as SegmentResource;
use Magendoo\CustomerSegment\Model\SegmentManagement;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SegmentManagementTest extends TestCase
{
    /** @var SegmentRepositoryInterface|MockObject */
    private $segmentRepository;

    /** @var SegmentResource|MockObject */
    private $segmentResource;

    /** @var CustomerCollectionFactory|MockObject */
    private $customerCollectionFactory;

    /** @var ResourceConnection|MockObject */
    private $resourceConnection;

    /** @var DateTime|MockObject */
    private $dateTime;

    /** @var Json|MockObject */
    private $jsonSerializer;

    /** @var CombineFactory|MockObject */
    private $combineFactory;

    /** @var SearchCriteriaBuilder|MockObject */
    private $searchCriteriaBuilder;

    /** @var FilterBuilder|MockObject */
    private $filterBuilder;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var ObjectManagerInterface|MockObject */
    private $objectManager;

    /** @var SegmentManagement */
    private $segmentManagement;

    protected function setUp(): void
    {
        $this->segmentRepository = $this->createMock(SegmentRepositoryInterface::class);
        $this->segmentResource = $this->createMock(SegmentResource::class);
        $this->customerCollectionFactory = $this->createMock(CustomerCollectionFactory::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->jsonSerializer = $this->createMock(Json::class);
        $this->combineFactory = $this->createMock(CombineFactory::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->filterBuilder = $this->createMock(FilterBuilder::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);

        $this->segmentManagement = new SegmentManagement(
            $this->segmentRepository,
            $this->segmentResource,
            $this->customerCollectionFactory,
            $this->resourceConnection,
            $this->dateTime,
            $this->jsonSerializer,
            $this->combineFactory,
            $this->searchCriteriaBuilder,
            $this->filterBuilder,
            $this->logger,
            $this->objectManager
        );
    }

    // ==================== refreshSegment() Tests ====================

    public function testRefreshSegmentWithActiveSegment(): void
    {
        $segmentId = 1;
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(true);
        $segment->method('getSegmentId')->willReturn($segmentId);

        $this->segmentRepository->expects($this->once())
            ->method('getById')
            ->with($segmentId)
            ->willReturn($segment);

        $this->segmentResource->expects($this->once())
            ->method('removeAllCustomers')
            ->with($segmentId);

        $segment->method('getConditionsSerialized')->willReturn(null);

        $this->segmentResource->expects($this->once())
            ->method('updateCustomerCount')
            ->with($segmentId, 0);

        $result = $this->segmentManagement->refreshSegment($segmentId);
        $this->assertEquals(0, $result);
    }

    public function testRefreshSegmentReturnsZeroForInactiveSegment(): void
    {
        $segmentId = 1;
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(false);

        $this->segmentRepository->expects($this->once())
            ->method('getById')
            ->with($segmentId)
            ->willReturn($segment);

        $this->segmentResource->expects($this->never())
            ->method('removeAllCustomers');

        $result = $this->segmentManagement->refreshSegment($segmentId);
        $this->assertEquals(0, $result);
    }

    public function testRefreshSegmentThrowsNoSuchEntityForInvalidId(): void
    {
        $segmentId = 999;

        $this->segmentRepository->expects($this->once())
            ->method('getById')
            ->with($segmentId)
            ->willThrowException(new NoSuchEntityException(__('Segment not found')));

        $this->expectException(NoSuchEntityException::class);
        $this->segmentManagement->refreshSegment($segmentId);
    }

    public function testRefreshSegmentClearsExistingCustomersBeforeReassigning(): void
    {
        $segmentId = 1;
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(true);
        $segment->method('getSegmentId')->willReturn($segmentId);

        $this->segmentRepository->method('getById')->willReturn($segment);

        $this->segmentResource->expects($this->once())
            ->method('removeAllCustomers')
            ->with($segmentId);

        $segment->method('getConditionsSerialized')->willReturn(null);
        $this->segmentResource->method('updateCustomerCount');

        $this->segmentManagement->refreshSegment($segmentId);
    }

    public function testRefreshSegmentUpdatesCustomerCount(): void
    {
        $segmentId = 1;
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(true);
        $segment->method('getSegmentId')->willReturn($segmentId);

        $this->segmentRepository->method('getById')->willReturn($segment);
        $this->segmentResource->method('removeAllCustomers');

        $segment->method('getConditionsSerialized')->willReturn(null);

        $this->segmentResource->expects($this->once())
            ->method('updateCustomerCount')
            ->with($segmentId, 0);

        $this->segmentManagement->refreshSegment($segmentId);
    }

    // ==================== Security: Condition Type Allowlist Tests ====================

    public function testCreateConditionRejectsDisallowedType(): void
    {
        $reflection = new \ReflectionClass($this->segmentManagement);
        $method = $reflection->getMethod('createCondition');
        $method->setAccessible(true);

        $this->logger->expects($this->once())
            ->method('error');

        $result = $method->invoke($this->segmentManagement, 'Malicious\Class\Name', []);
        $this->assertNull($result);
    }

    public function testCreateConditionLogsSecurityWarningForDisallowedType(): void
    {
        $reflection = new \ReflectionClass($this->segmentManagement);
        $method = $reflection->getMethod('createCondition');
        $method->setAccessible(true);

        $capturedMessage = null;
        $this->logger->expects($this->once())
            ->method('error')
            ->willReturnCallback(function ($message) use (&$capturedMessage) {
                $capturedMessage = (string) $message;
            });

        $method->invoke($this->segmentManagement, 'Malicious\Class\Name', []);
        
        $this->assertStringContainsString('Security:', $capturedMessage);
        $this->assertStringContainsString('Malicious', $capturedMessage);
    }

    public function testCreateConditionAcceptsAllowedCustomerType(): void
    {
        $reflection = new \ReflectionClass($this->segmentManagement);
        $method = $reflection->getMethod('createCondition');
        $method->setAccessible(true);

        // Verify that allowed types don't trigger security error
        $this->logger->expects($this->never())
            ->method('error');

        // Mock the ObjectManager to avoid actual instantiation
        $conditionMock = $this->createMock(\Magento\Rule\Model\Condition\AbstractCondition::class);
        $this->objectManager->expects($this->once())
            ->method('create')
            ->willReturn($conditionMock);

        $result = $method->invoke($this->segmentManagement, CustomerCondition::class, []);
        $this->assertNotNull($result);
    }

    // ==================== Customer-Segment Queries Tests ====================

    public function testGetCustomerSegmentIdsDelegatesToResource(): void
    {
        $customerId = 1;
        $expectedIds = [1, 2, 3];

        $this->segmentResource->expects($this->once())
            ->method('getCustomerSegmentIds')
            ->with($customerId)
            ->willReturn($expectedIds);

        $result = $this->segmentManagement->getCustomerSegmentIds($customerId);
        $this->assertEquals($expectedIds, $result);
    }

    public function testGetCustomerSegmentsReturnsFormattedData(): void
    {
        $customerId = 1;
        $segmentIds = [1, 2];

        $this->segmentResource->method('getCustomerSegmentIds')->willReturn($segmentIds);

        $segment1 = $this->createMock(SegmentInterface::class);
        $segment1->method('getSegmentId')->willReturn(1);
        $segment1->method('getName')->willReturn('Segment 1');
        $segment1->method('getDescription')->willReturn('Description 1');

        $segment2 = $this->createMock(SegmentInterface::class);
        $segment2->method('getSegmentId')->willReturn(2);
        $segment2->method('getName')->willReturn('Segment 2');
        $segment2->method('getDescription')->willReturn('Description 2');

        $this->segmentRepository->method('getById')
            ->willReturnMap([
                [1, $segment1],
                [2, $segment2],
            ]);

        $result = $this->segmentManagement->getCustomerSegments($customerId);

        $this->assertCount(2, $result);
        $this->assertEquals(['id' => 1, 'name' => 'Segment 1', 'description' => 'Description 1'], $result[0]);
        $this->assertEquals(['id' => 2, 'name' => 'Segment 2', 'description' => 'Description 2'], $result[1]);
    }

    public function testGetCustomerSegmentsReturnsEmptyArrayWhenNoSegments(): void
    {
        $customerId = 1;

        $this->segmentResource->method('getCustomerSegmentIds')->willReturn([]);

        $result = $this->segmentManagement->getCustomerSegments($customerId);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetCustomerSegmentsSkipsDeletedSegments(): void
    {
        $customerId = 1;
        $segmentIds = [1, 999];

        $this->segmentResource->method('getCustomerSegmentIds')->willReturn($segmentIds);

        $segment1 = $this->createMock(SegmentInterface::class);
        $segment1->method('getSegmentId')->willReturn(1);
        $segment1->method('getName')->willReturn('Segment 1');
        $segment1->method('getDescription')->willReturn('Description 1');

        $this->segmentRepository->method('getById')
            ->willReturnCallback(function ($id) use ($segment1) {
                if ($id === 999) {
                    throw new NoSuchEntityException(__('Segment not found'));
                }
                return $segment1;
            });

        $result = $this->segmentManagement->getCustomerSegments($customerId);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['id']);
    }

    public function testIsCustomerInSegmentReturnsTrueWhenPresent(): void
    {
        $customerId = 1;
        $segmentId = 2;

        $this->segmentResource->method('getCustomerSegmentIds')
            ->with($customerId)
            ->willReturn([1, 2, 3]);

        $result = $this->segmentManagement->isCustomerInSegment($customerId, $segmentId);
        $this->assertTrue($result);
    }

    public function testIsCustomerInSegmentReturnsFalseWhenAbsent(): void
    {
        $customerId = 1;
        $segmentId = 5;

        $this->segmentResource->method('getCustomerSegmentIds')
            ->with($customerId)
            ->willReturn([1, 2, 3]);

        $result = $this->segmentManagement->isCustomerInSegment($customerId, $segmentId);
        $this->assertFalse($result);
    }

    // ==================== Assign / Remove Tests ====================

    public function testAssignCustomerToSegmentDelegatesToResource(): void
    {
        $customerId = 1;
        $segmentId = 2;

        $this->segmentResource->expects($this->once())
            ->method('assignCustomer')
            ->with($segmentId, $customerId)
            ->willReturn(true);

        $result = $this->segmentManagement->assignCustomerToSegment($customerId, $segmentId);
        $this->assertTrue($result);
    }

    public function testAssignCustomerToSegmentThrowsCouldNotSaveOnFailure(): void
    {
        $customerId = 1;
        $segmentId = 2;

        $this->segmentResource->method('assignCustomer')
            ->willThrowException(new \Magento\Framework\Exception\LocalizedException(__('DB error')));

        $this->expectException(CouldNotSaveException::class);
        $this->segmentManagement->assignCustomerToSegment($customerId, $segmentId);
    }

    public function testRemoveCustomerFromSegmentDelegatesToResource(): void
    {
        $customerId = 1;
        $segmentId = 2;

        $this->segmentResource->expects($this->once())
            ->method('removeCustomer')
            ->with($segmentId, $customerId)
            ->willReturn(true);

        $result = $this->segmentManagement->removeCustomerFromSegment($customerId, $segmentId);
        $this->assertTrue($result);
    }

    public function testRemoveCustomerFromSegmentReturnsFalseOnFailure(): void
    {
        $customerId = 1;
        $segmentId = 2;

        $this->segmentResource->method('removeCustomer')
            ->willReturn(false);

        $result = $this->segmentManagement->removeCustomerFromSegment($customerId, $segmentId);
        $this->assertFalse($result);
    }

    // ==================== doesCustomerMatchSegment() Tests ====================

    public function testDoesCustomerMatchSegmentReturnsFalseForNonExistentSegment(): void
    {
        $customerId = 1;
        $segmentId = 999;

        $this->segmentRepository->method('getById')
            ->willThrowException(new NoSuchEntityException(__('Segment not found')));

        $result = $this->segmentManagement->doesCustomerMatchSegment($customerId, $segmentId);
        $this->assertFalse($result);
    }

    public function testDoesCustomerMatchSegmentReturnsFalseForInactiveSegment(): void
    {
        $customerId = 1;
        $segmentId = 1;

        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(false);

        $this->segmentRepository->method('getById')->willReturn($segment);

        $result = $this->segmentManagement->doesCustomerMatchSegment($customerId, $segmentId);
        $this->assertFalse($result);
    }

    public function testDoesCustomerMatchSegmentReturnsFalseWhenNoConditions(): void
    {
        $customerId = 1;
        $segmentId = 1;

        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(true);
        $segment->method('getConditionsSerialized')->willReturn(null);

        $this->segmentRepository->method('getById')->willReturn($segment);

        $result = $this->segmentManagement->doesCustomerMatchSegment($customerId, $segmentId);
        $this->assertFalse($result);
    }
}
