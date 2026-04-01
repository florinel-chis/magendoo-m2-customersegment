<?php
/**
 * Magendoo CustomerSegment Segment Management
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magendoo\CustomerSegment\Api\Data\SegmentInterface;
use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment as SegmentResource;
use Psr\Log\LoggerInterface;

/**
 * Segment Management Service
 */
class SegmentManagement implements SegmentManagementInterface
{
    /**
     * Allowlist of permitted condition types to prevent arbitrary class instantiation
     */
    private const ALLOWED_CONDITION_TYPES = [
        \Magendoo\CustomerSegment\Model\Condition\Combine::class,
        \Magendoo\CustomerSegment\Model\Condition\Customer::class,
        \Magendoo\CustomerSegment\Model\Condition\Order::class,
        \Magendoo\CustomerSegment\Model\Condition\Cart::class,
    ];

    /**
     * @var SegmentRepositoryInterface
     */
    protected SegmentRepositoryInterface $segmentRepository;

    /**
     * @var SegmentResource
     */
    protected SegmentResource $segmentResource;

    /**
     * @var CustomerCollectionFactory
     */
    protected CustomerCollectionFactory $customerCollectionFactory;

    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;

    /**
     * @var DateTime
     */
    protected DateTime $dateTime;

    /**
     * @var Json
     */
    protected Json $jsonSerializer;

    /**
     * @var Condition\CombineFactory
     */
    protected Condition\CombineFactory $combineFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    protected FilterBuilder $filterBuilder;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @param SegmentRepositoryInterface $segmentRepository
     * @param SegmentResource $segmentResource
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param DateTime $dateTime
     * @param Json $jsonSerializer
     * @param Condition\CombineFactory $combineFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        SegmentRepositoryInterface $segmentRepository,
        SegmentResource $segmentResource,
        CustomerCollectionFactory $customerCollectionFactory,
        ResourceConnection $resourceConnection,
        DateTime $dateTime,
        Json $jsonSerializer,
        Condition\CombineFactory $combineFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        LoggerInterface $logger,
        ?ObjectManagerInterface $objectManager = null
    ) {
        $this->segmentRepository = $segmentRepository;
        $this->segmentResource = $segmentResource;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->dateTime = $dateTime;
        $this->jsonSerializer = $jsonSerializer;
        $this->combineFactory = $combineFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->logger = $logger;
        $this->objectManager = $objectManager ?? \Magento\Framework\App\ObjectManager::getInstance();
    }

    /**
     * @inheritdoc
     */
    public function refreshSegment(int $segmentId): int
    {
        try {
            $segment = $this->segmentRepository->getById($segmentId);
        } catch (NoSuchEntityException $e) {
            throw $e;
        }

        if (!$segment->getIsActive()) {
            return 0;
        }

        $this->logger->info(__('Refreshing segment %1: %2', $segmentId, $segment->getName()));

        // Clear existing customers
        $this->segmentResource->removeAllCustomers($segmentId);

        // Get matching customers
        $matchingCustomers = $this->getMatchingCustomers($segment);

        if (empty($matchingCustomers)) {
            $this->segmentResource->updateCustomerCount($segmentId, 0);
            return 0;
        }

        // Batch insert customers
        $assignedCount = $this->segmentResource->massAssignCustomers($segmentId, $matchingCustomers);

        // Update customer count
        $this->segmentResource->updateCustomerCount($segmentId, $assignedCount);

        $this->logger->info(__('Segment %1 refreshed: %2 customers assigned', $segmentId, $assignedCount));

        return $assignedCount;
    }

    /**
     * @inheritdoc
     */
    public function refreshAllSegments(): void
    {
        $this->searchCriteriaBuilder->addFilter('is_active', 1);
        $searchCriteria = $this->searchCriteriaBuilder->create();

        $segments = $this->segmentRepository->getList($searchCriteria);

        foreach ($segments->getItems() as $segment) {
            try {
                $this->refreshSegment($segment->getSegmentId());
            } catch (\Exception $e) {
                $this->logger->error(__('Error refreshing segment %1: %2', 
                    $segment->getSegmentId(), 
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getCustomerSegmentIds(int $customerId): array
    {
        return $this->segmentResource->getCustomerSegmentIds($customerId);
    }

    /**
     * @inheritdoc
     */
    public function getCustomerSegments(int $customerId): array
    {
        $segmentIds = $this->getCustomerSegmentIds($customerId);

        if (empty($segmentIds)) {
            return [];
        }

        $segments = [];
        foreach ($segmentIds as $segmentId) {
            try {
                $segment = $this->segmentRepository->getById($segmentId);
                $segments[] = [
                    'id' => $segment->getSegmentId(),
                    'name' => $segment->getName(),
                    'description' => $segment->getDescription(),
                ];
            } catch (NoSuchEntityException $e) {
                // Skip deleted segments
            }
        }

        return $segments;
    }

    /**
     * @inheritdoc
     */
    public function assignCustomerToSegment(int $customerId, int $segmentId): bool
    {
        try {
            return $this->segmentResource->assignCustomer($segmentId, $customerId);
        } catch (LocalizedException $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }
    }

    /**
     * @inheritdoc
     */
    public function removeCustomerFromSegment(int $customerId, int $segmentId): bool
    {
        try {
            return $this->segmentResource->removeCustomer($segmentId, $customerId);
        } catch (LocalizedException $e) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function isCustomerInSegment(int $customerId, int $segmentId): bool
    {
        $segmentIds = $this->getCustomerSegmentIds($customerId);
        return in_array($segmentId, $segmentIds);
    }

    /**
     * @inheritdoc
     */
    public function doesCustomerMatchSegment(int $customerId, int $segmentId): bool
    {
        try {
            $segment = $this->segmentRepository->getById($segmentId);
        } catch (NoSuchEntityException $e) {
            return false;
        }

        if (!$segment->getIsActive()) {
            return false;
        }

        $conditions = $this->loadConditions($segment);
        if (!$conditions) {
            return false;
        }

        return $conditions->validate($customerId);
    }

    /**
     * @inheritdoc
     */
    public function massRefresh(array $segmentIds): int
    {
        $totalCustomers = 0;

        foreach ($segmentIds as $segmentId) {
            try {
                $totalCustomers += $this->refreshSegment($segmentId);
            } catch (\Exception $e) {
                $this->logger->error(__('Error in mass refresh for segment %1: %2', $segmentId, $e->getMessage()));
            }
        }

        return $totalCustomers;
    }

    /**
     * @inheritdoc
     */
    public function exportSegmentCustomers(int $segmentId, string $format): string
    {
        try {
            $segment = $this->segmentRepository->getById($segmentId);
        } catch (NoSuchEntityException $e) {
            throw $e;
        }

        $customers = $this->segmentResource->getSegmentCustomers($segmentId);

        if (empty($customers)) {
            return '';
        }

        $customerIds = array_column($customers, 'customer_id');

        // Get customer details
        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect(['email', 'firstname', 'lastname', 'created_at']);
        $collection->addAttributeToFilter('entity_id', ['in' => $customerIds]);

        if ($format === 'csv') {
            return $this->exportAsCsv($collection);
        }

        return $this->exportAsXml($collection);
    }

    /**
     * Get customers matching segment conditions
     *
     * @param SegmentInterface $segment
     * @return array Customer IDs
     */
    protected function getMatchingCustomers(SegmentInterface $segment): array
    {
        $conditions = $this->loadConditions($segment);
        
        if (!$conditions) {
            return [];
        }

        // Get all active customers
        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToFilter('entity_id', ['gt' => 0]);

        $matchingIds = [];
        
        // Process in batches to avoid memory issues
        $collection->setPageSize(1000);
        $pages = $collection->getLastPageNumber();

        for ($currentPage = 1; $currentPage <= $pages; $currentPage++) {
            $collection->setCurPage($currentPage);
            
            foreach ($collection as $customer) {
                if ($conditions->validate($customer->getId())) {
                    $matchingIds[] = $customer->getId();
                }
            }
            
            $collection->clear();
        }

        return $matchingIds;
    }

    /**
     * Load conditions from segment
     *
     * @param SegmentInterface $segment
     * @return Condition\Combine|null
     */
    protected function loadConditions(SegmentInterface $segment): ?Condition\Combine
    {
        $conditionsSerialized = $segment->getConditionsSerialized();
        
        if (!$conditionsSerialized) {
            return null;
        }

        try {
            $conditionsArray = $this->jsonSerializer->unserialize($conditionsSerialized);
        } catch (\Exception $e) {
            return null;
        }

        $combine = $this->combineFactory->create();
        $combine->setConditions([]);
        $combine->setAggregator($conditionsArray['aggregator'] ?? 'all');
        $combine->setValue($conditionsArray['value'] ?? true);

        if (!empty($conditionsArray['conditions'])) {
            $this->addChildConditions($combine, $conditionsArray['conditions']);
        }

        return $combine;
    }

    /**
     * Add child conditions recursively
     *
     * @param Condition\Combine $combine
     * @param array $conditions
     */
    protected function addChildConditions(Condition\Combine $combine, array $conditions): void
    {
        foreach ($conditions as $conditionData) {
            $type = $conditionData['type'] ?? '';
            
            if (str_contains($type, 'Combine')) {
                // Nested conditions
                $childCombine = $this->combineFactory->create();
                $childCombine->setAggregator($conditionData['aggregator'] ?? 'all');
                $childCombine->setValue($conditionData['value'] ?? true);
                
                if (!empty($conditionData['conditions'])) {
                    $this->addChildConditions($childCombine, $conditionData['conditions']);
                }
                
                $combine->addCondition($childCombine);
            } else {
                // Leaf condition
                $condition = $this->createCondition($type, $conditionData);
                if ($condition) {
                    $combine->addCondition($condition);
                }
            }
        }
    }

    /**
     * Create a condition instance
     *
     * @param string $type
     * @param array $data
     * @return \Magento\Rule\Model\Condition\AbstractCondition|null
     */
    protected function createCondition(string $type, array $data): ?\Magento\Rule\Model\Condition\AbstractCondition
    {
        // Security: Validate against allowlist to prevent arbitrary class instantiation
        if (!in_array($type, self::ALLOWED_CONDITION_TYPES, true)) {
            $this->logger->error(__('Security: Attempted to create condition with disallowed type: %1', $type));
            return null;
        }

        try {
            // Create the specific condition type (Customer, Order, Cart, etc.)
            $condition = $this->objectManager->create($type, ['data' => $data]);
            $condition->setAttribute($data['attribute'] ?? '');
            $condition->setOperator($data['operator'] ?? '==');
            $condition->setValue($data['value'] ?? '');
            return $condition;
        } catch (\Exception $e) {
            $this->logger->error(__('Error creating condition of type %1: %2', $type, $e->getMessage()));
            return null;
        }
    }

    /**
     * Export customers as CSV
     *
     * @param \Magento\Customer\Model\ResourceModel\Customer\Collection $collection
     * @return string
     */
    protected function exportAsCsv($collection): string
    {
        $output = "Customer ID,Email,First Name,Last Name,Created At\n";

        foreach ($collection as $customer) {
            $output .= sprintf(
                "%d,\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $customer->getId(),
                $customer->getEmail(),
                $customer->getFirstname(),
                $customer->getLastname(),
                $customer->getCreatedAt()
            );
        }

        return $output;
    }

    /**
     * Export customers as XML
     *
     * @param \Magento\Customer\Model\ResourceModel\Customer\Collection $collection
     * @return string
     */
    protected function exportAsXml($collection): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0"?><customers></customers>');

        foreach ($collection as $customer) {
            $customerNode = $xml->addChild('customer');
            $customerNode->addChild('customer_id', $customer->getId());
            $customerNode->addChild('email', htmlspecialchars($customer->getEmail()));
            $customerNode->addChild('first_name', htmlspecialchars($customer->getFirstname()));
            $customerNode->addChild('last_name', htmlspecialchars($customer->getLastname()));
            $customerNode->addChild('created_at', $customer->getCreatedAt());
        }

        return $xml->asXML();
    }
}
