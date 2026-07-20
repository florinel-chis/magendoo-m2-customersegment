<?php
/**
 * Magendoo CustomerSegment - SegmentManagement unit tests
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Magendoo\CustomerSegment\Api\Data\SegmentInterface;
use Magendoo\CustomerSegment\Api\Data\SegmentSearchResultsInterface;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magendoo\CustomerSegment\Model\Condition\Combine;
use Magendoo\CustomerSegment\Model\Condition\CombineFactory;
use Magendoo\CustomerSegment\Model\Condition\Customer as CustomerCondition;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment as SegmentResource;
use Magendoo\CustomerSegment\Model\SegmentManagement;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
#[CoversClass(SegmentManagement::class)]
class SegmentManagementTest extends TestCase
{
    /** @var SegmentRepositoryInterface&MockObject */
    private $segmentRepository;

    /** @var SegmentResource&MockObject */
    private $segmentResource;

    /** @var CustomerCollectionFactory&MockObject */
    private $customerCollectionFactory;

    /** @var ResourceConnection&MockObject */
    private $resourceConnection;

    /** @var DateTime&MockObject */
    private $dateTime;

    /** @var Json&MockObject */
    private $jsonSerializer;

    /** @var CombineFactory&MockObject */
    private $combineFactory;

    /** @var SearchCriteriaBuilder&MockObject */
    private $searchCriteriaBuilder;

    /** @var FilterBuilder&MockObject */
    private $filterBuilder;

    /** @var LoggerInterface&MockObject */
    private $logger;

    /** @var ObjectManagerInterface&MockObject */
    private $objectManager;

    /** @var ManagerInterface&MockObject */
    private $eventManager;

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
        $this->eventManager = $this->createMock(ManagerInterface::class);

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
            $this->eventManager,
            $this->objectManager
        );
    }

    /**
     * Build a lightweight customer row double exposing only the accessors the exporter calls.
     *
     * Avoids mocking Magento's Customer model (whose getEmail/getFirstname/... are magic __call
     * methods that PHPUnit 12 can no longer stub via the removed MockBuilder::addMethods()).
     */
    private function makeCustomerRow(
        mixed $id,
        string $email,
        string $firstname,
        string $lastname,
        string $createdAt
    ): object {
        return new class ($id, $email, $firstname, $lastname, $createdAt) {
            public function __construct(
                private mixed $id,
                private string $email,
                private string $firstname,
                private string $lastname,
                private string $createdAt
            ) {
            }

            public function getId(): mixed
            {
                return $this->id;
            }

            public function getEmail(): string
            {
                return $this->email;
            }

            public function getFirstname(): string
            {
                return $this->firstname;
            }

            public function getLastname(): string
            {
                return $this->lastname;
            }

            public function getCreatedAt(): string
            {
                return $this->createdAt;
            }
        };
    }

    /**
     * Build a customer collection double that yields the given rows on iteration.
     *
     * @param object[] $rows
     * @return \Magento\Customer\Model\ResourceModel\Customer\Collection&MockObject
     */
    private function makeCustomerCollection(array $rows)
    {
        $collection = $this->createMock(\Magento\Customer\Model\ResourceModel\Customer\Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($rows));
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();

        return $collection;
    }

    // ==================== refreshSegment() Tests ====================

    public function testRefreshSegmentWithActiveSegmentAndNoConditions(): void
    {
        $segmentId = 1;
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(true);
        $segment->method('getSegmentId')->willReturn($segmentId);
        $segment->method('getName')->willReturn('Empty Segment');
        $segment->method('getConditionsSerialized')->willReturn(null);

        $this->segmentRepository->expects($this->once())
            ->method('getById')
            ->with($segmentId)
            ->willReturn($segment);

        // No conditions -> empty matching set -> atomic replace with an empty list.
        $this->segmentResource->expects($this->once())
            ->method('replaceCustomers')
            ->with($segmentId, [])
            ->willReturn(0);

        $this->segmentResource->expects($this->once())
            ->method('updateCustomerCount')
            ->with($segmentId, 0);

        $result = $this->segmentManagement->refreshSegment($segmentId);
        $this->assertSame(0, $result);
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

        $this->segmentResource->expects($this->never())->method('replaceCustomers');
        $this->segmentResource->expects($this->never())->method('updateCustomerCount');

        $result = $this->segmentManagement->refreshSegment($segmentId);
        $this->assertSame(0, $result);
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

    public function testRefreshSegmentReplacesMembershipAtomically(): void
    {
        $segmentId = 1;
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(true);
        $segment->method('getSegmentId')->willReturn($segmentId);
        $segment->method('getName')->willReturn('Segment');
        $segment->method('getConditionsSerialized')->willReturn(null);

        $this->segmentRepository->method('getById')->willReturn($segment);

        // The whole remove-all + reassign happens in a single replaceCustomers call (one transaction).
        $this->segmentResource->expects($this->once())
            ->method('replaceCustomers')
            ->with($segmentId, [])
            ->willReturn(0);
        $this->segmentResource->method('updateCustomerCount');

        $this->segmentManagement->refreshSegment($segmentId);
    }

    public function testRefreshSegmentUpdatesCustomerCountFromAssignedTotal(): void
    {
        $segmentId = 1;
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(true);
        $segment->method('getSegmentId')->willReturn($segmentId);
        $segment->method('getName')->willReturn('Segment');
        $segment->method('getConditionsSerialized')->willReturn(null);

        $this->segmentRepository->method('getById')->willReturn($segment);

        // replaceCustomers reports the true post-assignment membership count.
        $this->segmentResource->method('replaceCustomers')->willReturn(7);

        $this->segmentResource->expects($this->once())
            ->method('updateCustomerCount')
            ->with($segmentId, 7);

        $result = $this->segmentManagement->refreshSegment($segmentId);
        $this->assertSame(7, $result);
    }

    // ==================== Security: Condition Type Allowlist Tests ====================

    public function testCreateConditionRejectsDisallowedType(): void
    {
        $reflection = new \ReflectionClass($this->segmentManagement);
        $method = $reflection->getMethod('createCondition');
        $method->setAccessible(true);

        $this->logger->expects($this->once())->method('error');

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

        // Allowed types must NOT trigger the security error path.
        $this->logger->expects($this->never())->method('error');

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
        $this->assertSame($expectedIds, $result);
    }

    public function testGetCustomerSegmentsReturnsFormattedData(): void
    {
        $customerId = 1;

        $this->segmentResource->method('getCustomerSegmentIds')->willReturn([1, 2]);

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
        $this->segmentResource->method('getCustomerSegmentIds')->willReturn([]);

        $result = $this->segmentManagement->getCustomerSegments(1);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetCustomerSegmentsSkipsDeletedSegments(): void
    {
        $this->segmentResource->method('getCustomerSegmentIds')->willReturn([1, 999]);

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

        $result = $this->segmentManagement->getCustomerSegments(1);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['id']);
    }

    public function testIsCustomerInSegmentReturnsTrueWhenPresent(): void
    {
        $this->segmentResource->method('getCustomerSegmentIds')
            ->with(1)
            ->willReturn([1, 2, 3]);

        $this->assertTrue($this->segmentManagement->isCustomerInSegment(1, 2));
    }

    public function testIsCustomerInSegmentReturnsFalseWhenAbsent(): void
    {
        $this->segmentResource->method('getCustomerSegmentIds')
            ->with(1)
            ->willReturn([1, 2, 3]);

        $this->assertFalse($this->segmentManagement->isCustomerInSegment(1, 5));
    }

    // ==================== Assign / Remove Tests ====================

    public function testAssignCustomerToSegmentDelegatesToResource(): void
    {
        $this->segmentResource->expects($this->once())
            ->method('assignCustomer')
            ->with(2, 1)
            ->willReturn(true);

        $this->assertTrue($this->segmentManagement->assignCustomerToSegment(1, 2));
    }

    public function testAssignCustomerToSegmentThrowsCouldNotSaveOnFailure(): void
    {
        $this->segmentResource->method('assignCustomer')
            ->willThrowException(new LocalizedException(__('DB error')));

        $this->expectException(CouldNotSaveException::class);
        $this->segmentManagement->assignCustomerToSegment(1, 2);
    }

    public function testRemoveCustomerFromSegmentDelegatesToResource(): void
    {
        $this->segmentResource->expects($this->once())
            ->method('removeCustomer')
            ->with(2, 1)
            ->willReturn(true);

        $this->assertTrue($this->segmentManagement->removeCustomerFromSegment(1, 2));
    }

    public function testRemoveCustomerFromSegmentReturnsFalseOnFailure(): void
    {
        $this->segmentResource->method('removeCustomer')->willReturn(false);

        $this->assertFalse($this->segmentManagement->removeCustomerFromSegment(1, 2));
    }

    // ==================== doesCustomerMatchSegment() Tests ====================

    public function testDoesCustomerMatchSegmentReturnsFalseForNonExistentSegment(): void
    {
        $this->segmentRepository->method('getById')
            ->willThrowException(new NoSuchEntityException(__('Segment not found')));

        $this->assertFalse($this->segmentManagement->doesCustomerMatchSegment(1, 999));
    }

    public function testDoesCustomerMatchSegmentReturnsFalseForInactiveSegment(): void
    {
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(false);

        $this->segmentRepository->method('getById')->willReturn($segment);

        $this->assertFalse($this->segmentManagement->doesCustomerMatchSegment(1, 1));
    }

    public function testDoesCustomerMatchSegmentReturnsFalseWhenNoConditions(): void
    {
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(true);
        $segment->method('getConditionsSerialized')->willReturn(null);

        $this->segmentRepository->method('getById')->willReturn($segment);

        $this->assertFalse($this->segmentManagement->doesCustomerMatchSegment(1, 1));
    }

    public function testDoesCustomerMatchSegmentReturnsTrueWhenConditionsValidate(): void
    {
        $customerId = 1;

        $serialized = '{"aggregator":"all","value":true,"conditions":['
            . '{"type":"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Combine",'
            . '"aggregator":"all","value":true}]}';

        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(true);
        $segment->method('getConditionsSerialized')->willReturn($serialized);

        $this->segmentRepository->method('getById')->willReturn($segment);

        $this->jsonSerializer->method('unserialize')
            ->with($serialized)
            ->willReturn([
                'aggregator' => 'all',
                'value' => true,
                'conditions' => [
                    ['type' => Combine::class, 'aggregator' => 'all', 'value' => true],
                ],
            ]);

        $combine = $this->createMock(Combine::class);
        $combine->method('validate')->with($customerId)->willReturn(true);

        $this->combineFactory->method('create')->willReturn($combine);

        $this->assertTrue($this->segmentManagement->doesCustomerMatchSegment($customerId, 1));
    }

    public function testDoesCustomerMatchSegmentReturnsFalseForEmptyConditionTree(): void
    {
        $serialized = '{"aggregator":"all","value":true,"conditions":[]}';

        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(true);
        $segment->method('getConditionsSerialized')->willReturn($serialized);

        $this->segmentRepository->method('getById')->willReturn($segment);

        $this->jsonSerializer->method('unserialize')
            ->with($serialized)
            ->willReturn(['aggregator' => 'all', 'value' => true, 'conditions' => []]);

        // An explicitly-empty condition tree matches nobody (never the entire customer base).
        $this->assertFalse($this->segmentManagement->doesCustomerMatchSegment(1, 1));
    }

    // ==================== export Tests ====================

    public function testExportSegmentCustomersAsCsvReturnsCsvContent(): void
    {
        $segmentId = 1;

        $segment = $this->createMock(SegmentInterface::class);
        $this->segmentRepository->method('getById')->willReturn($segment);

        $this->segmentResource->method('getSegmentCustomers')->willReturn([
            ['customer_id' => 1],
        ]);

        $collection = $this->makeCustomerCollection([
            $this->makeCustomerRow(1, 'test@example.com', 'John', 'Doe', '2023-01-15 10:00:00'),
        ]);
        $this->customerCollectionFactory->method('create')->willReturn($collection);

        $result = $this->segmentManagement->exportSegmentCustomers($segmentId, 'csv');

        $this->assertStringContainsString('Customer ID', $result);
        $this->assertStringContainsString('Email', $result);
        $this->assertStringContainsString('test@example.com', $result);
    }

    public function testExportSegmentCustomersAsXmlReturnsXmlContent(): void
    {
        $segmentId = 1;

        $segment = $this->createMock(SegmentInterface::class);
        $this->segmentRepository->method('getById')->willReturn($segment);

        $this->segmentResource->method('getSegmentCustomers')->willReturn([
            ['customer_id' => 1],
        ]);

        $collection = $this->makeCustomerCollection([
            $this->makeCustomerRow('1', 'test@example.com', 'John', 'Doe', '2023-01-15 10:00:00'),
        ]);
        $this->customerCollectionFactory->method('create')->willReturn($collection);

        $result = $this->segmentManagement->exportSegmentCustomers($segmentId, 'xml');

        $this->assertStringContainsString('<?xml version="1.0"?>', $result);
        $this->assertStringContainsString('<customers>', $result);
        $this->assertStringContainsString('test@example.com', $result);
    }

    public function testExportSegmentCustomersRejectsUnknownFormat(): void
    {
        // Unknown format is now validated up-front and rejected (no silent XML fallback).
        // Validation happens before the segment is loaded.
        $this->segmentRepository->expects($this->never())->method('getById');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unsupported export format "json"');

        $this->segmentManagement->exportSegmentCustomers(1, 'json');
    }

    public function testExportSegmentCustomersThrowsNoSuchEntityForInvalidSegment(): void
    {
        $this->segmentRepository->method('getById')
            ->willThrowException(new NoSuchEntityException(__('Segment not found')));

        $this->expectException(NoSuchEntityException::class);
        $this->segmentManagement->exportSegmentCustomers(999, 'csv');
    }

    public function testExportSegmentCustomersReturnsEmptyStringWhenNoCustomers(): void
    {
        $segment = $this->createMock(SegmentInterface::class);
        $this->segmentRepository->method('getById')->willReturn($segment);
        $this->segmentResource->method('getSegmentCustomers')->willReturn([]);

        $this->assertSame('', $this->segmentManagement->exportSegmentCustomers(1, 'csv'));
    }

    public function testExportCsvEscapesSpecialCharacters(): void
    {
        $segmentId = 1;

        $segment = $this->createMock(SegmentInterface::class);
        $this->segmentRepository->method('getById')->willReturn($segment);

        $this->segmentResource->method('getSegmentCustomers')->willReturn([
            ['customer_id' => 1],
        ]);

        $collection = $this->makeCustomerCollection([
            // Quote in firstname, comma in lastname.
            $this->makeCustomerRow(1, 'test@example.com', 'John"Smith', 'Doe, Jr.', '2023-01-15 10:00:00'),
        ]);
        $this->customerCollectionFactory->method('create')->willReturn($collection);

        $result = $this->segmentManagement->exportSegmentCustomers($segmentId, 'csv');

        // Field containing a comma is wrapped in quotes; embedded quotes are doubled.
        $this->assertStringContainsString('"Doe, Jr."', $result);
        $this->assertStringContainsString('John""Smith', $result);
    }

    public function testExportCsvNeutralizesFormulaInjection(): void
    {
        $segmentId = 1;

        $segment = $this->createMock(SegmentInterface::class);
        $this->segmentRepository->method('getById')->willReturn($segment);

        $this->segmentResource->method('getSegmentCustomers')->willReturn([
            ['customer_id' => 1],
        ]);

        $collection = $this->makeCustomerCollection([
            // A leading '=' would be evaluated as a formula by spreadsheet software.
            $this->makeCustomerRow(1, '=cmd|calc', 'Jane', 'Roe', '2023-01-15 10:00:00'),
        ]);
        $this->customerCollectionFactory->method('create')->willReturn($collection);

        $result = $this->segmentManagement->exportSegmentCustomers($segmentId, 'csv');

        // The dangerous field is prefixed with a single quote so it is treated as literal text.
        $this->assertStringContainsString("'=cmd", $result);
    }

    // ==================== refreshAllSegments() / massRefresh() Tests ====================

    public function testRefreshAllSegmentsCallsRefreshForEachActiveSegment(): void
    {
        $listItem1 = $this->createMock(SegmentInterface::class);
        $listItem1->method('getSegmentId')->willReturn(1);
        $listItem2 = $this->createMock(SegmentInterface::class);
        $listItem2->method('getSegmentId')->willReturn(2);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $searchResults = $this->createMock(SegmentSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$listItem1, $listItem2]);
        $this->segmentRepository->method('getList')->willReturn($searchResults);

        // Each refreshSegment reloads the full segment via getById.
        $fullSegment = $this->createMock(SegmentInterface::class);
        $fullSegment->method('getIsActive')->willReturn(true);
        $fullSegment->method('getName')->willReturn('Segment');
        $fullSegment->method('getConditionsSerialized')->willReturn(null);
        $this->segmentRepository->method('getById')->willReturn($fullSegment);

        $this->segmentResource->expects($this->exactly(2))
            ->method('replaceCustomers')
            ->willReturn(3);
        $this->segmentResource->expects($this->exactly(2))
            ->method('updateCustomerCount');

        $this->segmentManagement->refreshAllSegments();
    }

    public function testRefreshAllSegmentsLogsErrorOnException(): void
    {
        $listItem = $this->createMock(SegmentInterface::class);
        $listItem->method('getSegmentId')->willReturn(1);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $searchResults = $this->createMock(SegmentSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$listItem]);
        $this->segmentRepository->method('getList')->willReturn($searchResults);

        $fullSegment = $this->createMock(SegmentInterface::class);
        $fullSegment->method('getIsActive')->willReturn(true);
        $fullSegment->method('getName')->willReturn('Segment');
        $fullSegment->method('getConditionsSerialized')->willReturn(null);
        $this->segmentRepository->method('getById')->willReturn($fullSegment);

        // The atomic replace fails -> the error is logged, not re-thrown.
        $this->segmentResource->method('replaceCustomers')
            ->willThrowException(new \Exception('DB Error'));

        $this->logger->expects($this->once())->method('error');

        $this->segmentManagement->refreshAllSegments();
    }

    public function testMassRefreshSumsCountsForEachSegment(): void
    {
        $segmentIds = [1, 2, 3];

        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(true);
        $segment->method('getName')->willReturn('Segment');
        $segment->method('getConditionsSerialized')->willReturn(null);
        $this->segmentRepository->method('getById')->willReturn($segment);

        $this->segmentResource->expects($this->exactly(3))
            ->method('replaceCustomers')
            ->willReturn(1);
        $this->segmentResource->expects($this->exactly(3))
            ->method('updateCustomerCount');

        $result = $this->segmentManagement->massRefresh($segmentIds);
        $this->assertSame(3, $result);
    }

    public function testMassRefreshLogsErrorAndContinuesOnException(): void
    {
        $segmentIds = [1, 2];

        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getIsActive')->willReturn(true);
        $segment->method('getName')->willReturn('Segment');
        $segment->method('getConditionsSerialized')->willReturn(null);
        $this->segmentRepository->method('getById')->willReturn($segment);

        // First segment succeeds (count 1), second throws during the atomic replace.
        $callCount = 0;
        $this->segmentResource->method('replaceCustomers')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 2) {
                    throw new \Exception('DB Error');
                }
                return 1;
            });

        $this->logger->expects($this->once())->method('error');

        $result = $this->segmentManagement->massRefresh($segmentIds);
        $this->assertSame(1, $result);
    }
}
