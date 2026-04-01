<?php
/**
 * Magendoo CustomerSegment Segment Repository
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model;

use Magendoo\CustomerSegment\Api\Data\SegmentInterface;
use Magendoo\CustomerSegment\Api\Data\SegmentSearchResultsInterface;
use Magendoo\CustomerSegment\Api\Data\SegmentSearchResultsInterfaceFactory;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment as ResourceSegment;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment\CollectionFactory as SegmentCollectionFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Reflection\DataObjectProcessor;

/**
 * Segment Repository
 */
class SegmentRepository implements SegmentRepositoryInterface
{
    /**
     * @var ResourceSegment
     */
    protected ResourceSegment $resource;

    /**
     * @var SegmentFactory
     */
    protected SegmentFactory $segmentFactory;

    /**
     * @var SegmentCollectionFactory
     */
    protected SegmentCollectionFactory $segmentCollectionFactory;

    /**
     * @var SegmentSearchResultsInterfaceFactory
     */
    protected SegmentSearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @var DataObjectHelper
     */
    protected DataObjectHelper $dataObjectHelper;

    /**
     * @var DataObjectProcessor
     */
    protected DataObjectProcessor $dataObjectProcessor;

    /**
     * @var JoinProcessorInterface
     */
    protected JoinProcessorInterface $extensionAttributesJoinProcessor;

    /**
     * @var CollectionProcessorInterface
     */
    protected CollectionProcessorInterface $collectionProcessor;

    /**
     * @var ExtensibleDataObjectConverter
     */
    protected ExtensibleDataObjectConverter $extensibleDataObjectConverter;

    /**
     * @var array
     */
    protected array $registry = [];

    /**
     * @param ResourceSegment $resource
     * @param SegmentFactory $segmentFactory
     * @param SegmentCollectionFactory $segmentCollectionFactory
     * @param SegmentSearchResultsInterfaceFactory $searchResultsFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param DataObjectProcessor $dataObjectProcessor
     * @param JoinProcessorInterface $extensionAttributesJoinProcessor
     * @param CollectionProcessorInterface $collectionProcessor
     * @param ExtensibleDataObjectConverter $extensibleDataObjectConverter
     */
    public function __construct(
        ResourceSegment $resource,
        SegmentFactory $segmentFactory,
        SegmentCollectionFactory $segmentCollectionFactory,
        SegmentSearchResultsInterfaceFactory $searchResultsFactory,
        DataObjectHelper $dataObjectHelper,
        DataObjectProcessor $dataObjectProcessor,
        JoinProcessorInterface $extensionAttributesJoinProcessor,
        CollectionProcessorInterface $collectionProcessor,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter
    ) {
        $this->resource = $resource;
        $this->segmentFactory = $segmentFactory;
        $this->segmentCollectionFactory = $segmentCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataObjectProcessor = $dataObjectProcessor;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
        $this->collectionProcessor = $collectionProcessor;
        $this->extensibleDataObjectConverter = $extensibleDataObjectConverter;
    }

    /**
     * @inheritdoc
     */
    public function save(SegmentInterface $segment): SegmentInterface
    {
        $segmentData = $this->extensibleDataObjectConverter->toNestedArray(
            $segment,
            [],
            SegmentInterface::class
        );

        $segmentModel = $this->segmentFactory->create();
        
        if ($segment->getSegmentId()) {
            $this->resource->load($segmentModel, $segment->getSegmentId());
        }
        
        $segmentModel->addData($segmentData);

        try {
            $this->resource->save($segmentModel);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        // Clear registry cache
        unset($this->registry[$segmentModel->getId()]);

        return $this->getById((int) $segmentModel->getId());
    }

    /**
     * @inheritdoc
     */
    public function getById(int $segmentId): SegmentInterface
    {
        if (!isset($this->registry[$segmentId])) {
            $segment = $this->segmentFactory->create();
            $this->resource->load($segment, $segmentId);
            
            if (!$segment->getId()) {
                throw new NoSuchEntityException(__('Segment with id "%1" does not exist.', $segmentId));
            }
            
            $this->registry[$segmentId] = $segment;
        }

        return $this->registry[$segmentId];
    }

    /**
     * @inheritdoc
     */
    public function get(int $segmentId, ?int $storeId = null): SegmentInterface
    {
        // Store ID handling can be added here for multi-store specific segments
        return $this->getById($segmentId);
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SegmentSearchResultsInterface
    {
        $collection = $this->segmentCollectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setTotalCount($collection->getSize());

        $segments = [];
        /** @var Segment $segmentModel */
        foreach ($collection->getItems() as $segmentModel) {
            $segments[] = $this->convertToDataModel($segmentModel);
        }

        $searchResults->setItems($segments);
        return $searchResults;
    }

    /**
     * @inheritdoc
     */
    public function delete(SegmentInterface $segment): bool
    {
        try {
            $segmentModel = $this->segmentFactory->create();
            $this->resource->load($segmentModel, $segment->getSegmentId());
            $this->resource->delete($segmentModel);
            unset($this->registry[$segment->getSegmentId()]);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $segmentId): bool
    {
        return $this->delete($this->getById($segmentId));
    }

    /**
     * Convert model to data interface
     *
     * @param Segment $segment
     * @return SegmentInterface
     */
    protected function convertToDataModel(Segment $segment): SegmentInterface
    {
        $segmentData = $this->dataObjectProcessor->buildOutputDataArray(
            $segment,
            SegmentInterface::class
        );

        $segmentDto = $this->segmentFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $segmentDto,
            $segmentData,
            SegmentInterface::class
        );

        return $segmentDto;
    }
}
