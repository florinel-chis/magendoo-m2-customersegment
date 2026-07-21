<?php
/**
 * Magendoo CustomerSegment - Segment repository (partial-update-safe persistence)
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
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
 * Persists and loads customer segments, merging partial DTOs onto stored rows.
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
        $segmentModel = $this->segmentFactory->create();
        $segmentId = $segment->getSegmentId();

        if ($segmentId) {
            // Load the persisted row first so an unset field in a partial DTO
            // (e.g. REST PUT {segment_id, name}) keeps its stored value instead
            // of being reset to a defaulted getter value.
            $this->resource->load($segmentModel, $segmentId);
            if (!$segmentModel->getId()) {
                throw new NoSuchEntityException(
                    __('Segment with id "%1" does not exist.', $segmentId)
                );
            }
        }

        // Overlay only the fields the caller actually provided.
        $providedData = $this->extractProvidedData($segment);

        // customer_count and last_refreshed are server-managed (refresh path only);
        // they must never be settable through save().
        unset(
            $providedData[SegmentInterface::CUSTOMER_COUNT],
            $providedData[SegmentInterface::LAST_REFRESHED]
        );

        $segmentModel->addData($providedData);

        try {
            $this->resource->save($segmentModel);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $this->getById((int) $segmentModel->getId());
    }

    /**
     * Extract only the fields explicitly provided on the incoming data object
     *
     * The raw data array of a DataObject/extensible model contains only the keys
     * that were set (via the REST input processor or the admin form), so unset
     * fields are absent and therefore preserved on a partial update. A non
     * DataObject implementation falls back to the full nested array.
     *
     * @param SegmentInterface $segment
     * @return array
     */
    private function extractProvidedData(SegmentInterface $segment): array
    {
        if ($segment instanceof \Magento\Framework\DataObject) {
            return $segment->getData();
        }

        return $this->extensibleDataObjectConverter->toNestedArray(
            $segment,
            [],
            SegmentInterface::class
        );
    }

    /**
     * @inheritdoc
     */
    public function getById(int $segmentId): SegmentInterface
    {
        $segment = $this->segmentFactory->create();
        $this->resource->load($segment, $segmentId);

        if (!$segment->getId()) {
            throw new NoSuchEntityException(__('Segment with id "%1" does not exist.', $segmentId));
        }

        return $segment;
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
        $segmentModel = $this->segmentFactory->create();
        $this->resource->load($segmentModel, $segment->getSegmentId());

        if (!$segmentModel->getId()) {
            throw new NoSuchEntityException(
                __('Segment with id "%1" does not exist.', $segment->getSegmentId())
            );
        }

        try {
            $this->resource->delete($segmentModel);
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
