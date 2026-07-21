<?php
/**
 * Magendoo CustomerSegment - SegmentRepository unit tests
 *
 * Focuses on the REST partial-update (no-clobber) contract: an unset field on an
 * incoming DTO must never overwrite the stored value, and the server-managed
 * customer_count / last_refreshed columns can never be set through save().
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model;

use Magendoo\CustomerSegment\Api\Data\SegmentInterface;
use Magendoo\CustomerSegment\Api\Data\SegmentSearchResultsInterface;
use Magendoo\CustomerSegment\Api\Data\SegmentSearchResultsInterfaceFactory;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment as ResourceSegment;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment\Collection as SegmentCollection;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment\CollectionFactory as SegmentCollectionFactory;
use Magendoo\CustomerSegment\Model\Segment;
use Magendoo\CustomerSegment\Model\SegmentFactory;
use Magendoo\CustomerSegment\Model\SegmentRepository;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Reflection\DataObjectProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SegmentRepository::class)]
class SegmentRepositoryTest extends TestCase
{
    /** @var ResourceSegment&MockObject */
    private ResourceSegment $resource;

    /** @var SegmentFactory&MockObject */
    private SegmentFactory $segmentFactory;

    /** @var SegmentCollectionFactory&MockObject */
    private SegmentCollectionFactory $collectionFactory;

    /** @var SegmentSearchResultsInterfaceFactory&MockObject */
    private SegmentSearchResultsInterfaceFactory $searchResultsFactory;

    /** @var DataObjectHelper&MockObject */
    private DataObjectHelper $dataObjectHelper;

    /** @var DataObjectProcessor&MockObject */
    private DataObjectProcessor $dataObjectProcessor;

    /** @var CollectionProcessorInterface&MockObject */
    private CollectionProcessorInterface $collectionProcessor;

    /** @var SegmentRepository */
    private SegmentRepository $repository;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(ResourceSegment::class);
        $this->segmentFactory = $this->createMock(SegmentFactory::class);
        $this->collectionFactory = $this->createMock(SegmentCollectionFactory::class);
        $this->searchResultsFactory = $this->createMock(SegmentSearchResultsInterfaceFactory::class);
        $this->dataObjectHelper = $this->createMock(DataObjectHelper::class);
        $this->dataObjectProcessor = $this->createMock(DataObjectProcessor::class);
        $this->collectionProcessor = $this->createMock(CollectionProcessorInterface::class);

        $this->repository = new SegmentRepository(
            $this->resource,
            $this->segmentFactory,
            $this->collectionFactory,
            $this->searchResultsFactory,
            $this->dataObjectHelper,
            $this->dataObjectProcessor,
            $this->createMock(JoinProcessorInterface::class),
            $this->collectionProcessor,
            $this->createMock(ExtensibleDataObjectConverter::class)
        );
    }

    /**
     * Build a Segment model mock exposing the real data-bag methods used by save().
     *
     * @param array $methods
     * @return Segment&MockObject
     */
    private function segmentModel(array $methods): Segment
    {
        return $this->getMockBuilder(Segment::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }

    #[Test]
    public function savePartialUpdateMergesProvidedFieldsOntoLoadedRowWithoutClobbering(): void
    {
        // Incoming partial DTO: caller only set segment_id + name (e.g. REST PUT).
        $incoming = $this->segmentModel(['getSegmentId', 'getData']);
        $incoming->method('getSegmentId')->willReturn(7);
        $incoming->method('getData')->willReturn([
            'segment_id' => 7,
            'name' => 'Renamed',
        ]);

        // The model the repository loads/saves and re-reads.
        $workModel = $this->segmentModel(['getId', 'addData']);
        $workModel->method('getId')->willReturn(7);

        // Only the provided fields must be overlaid onto the loaded row.
        $workModel->expects($this->once())
            ->method('addData')
            ->with(['segment_id' => 7, 'name' => 'Renamed']);

        $this->segmentFactory->method('create')->willReturn($workModel);
        $this->resource->expects($this->atLeastOnce())->method('load');
        $this->resource->expects($this->once())->method('save')->with($workModel);

        $result = $this->repository->save($incoming);

        $this->assertSame($workModel, $result);
    }

    #[Test]
    public function saveStripsServerManagedColumns(): void
    {
        // Even if the caller supplies customer_count / last_refreshed they must be dropped.
        $incoming = $this->segmentModel(['getSegmentId', 'getData']);
        $incoming->method('getSegmentId')->willReturn(3);
        $incoming->method('getData')->willReturn([
            'segment_id' => 3,
            'name' => 'Keep',
            'customer_count' => 999,
            'last_refreshed' => '2000-01-01 00:00:00',
        ]);

        $workModel = $this->segmentModel(['getId', 'addData']);
        $workModel->method('getId')->willReturn(3);
        $workModel->expects($this->once())
            ->method('addData')
            ->with($this->callback(function (array $data): bool {
                return !array_key_exists('customer_count', $data)
                    && !array_key_exists('last_refreshed', $data)
                    && $data['name'] === 'Keep';
            }));

        $this->segmentFactory->method('create')->willReturn($workModel);
        $this->resource->expects($this->once())->method('save');

        $this->repository->save($incoming);
    }

    #[Test]
    public function saveThrowsWhenUpdatingMissingSegment(): void
    {
        $incoming = $this->segmentModel(['getSegmentId', 'getData']);
        $incoming->method('getSegmentId')->willReturn(42);
        $incoming->method('getData')->willReturn(['segment_id' => 42]);

        // Loaded model has no id => row does not exist.
        $workModel = $this->segmentModel(['getId', 'addData']);
        $workModel->method('getId')->willReturn(null);
        $this->segmentFactory->method('create')->willReturn($workModel);
        $this->resource->expects($this->never())->method('save');

        $this->expectException(NoSuchEntityException::class);
        $this->repository->save($incoming);
    }

    #[Test]
    public function saveNewSegmentSkipsPreloadAndPersists(): void
    {
        $incoming = $this->segmentModel(['getSegmentId', 'getData']);
        $incoming->method('getSegmentId')->willReturn(null);
        $incoming->method('getData')->willReturn(['name' => 'Fresh']);

        $workModel = $this->segmentModel(['getId', 'addData']);
        // After save the resource model assigns an id; getById re-reads it.
        $workModel->method('getId')->willReturn(11);
        $workModel->expects($this->once())->method('addData')->with(['name' => 'Fresh']);

        $this->segmentFactory->method('create')->willReturn($workModel);
        // Only getById's load runs (no preload for a new entity).
        $this->resource->expects($this->once())->method('load');
        $this->resource->expects($this->once())->method('save');

        $this->assertSame($workModel, $this->repository->save($incoming));
    }

    #[Test]
    public function saveWrapsResourceFailureInCouldNotSave(): void
    {
        $incoming = $this->segmentModel(['getSegmentId', 'getData']);
        $incoming->method('getSegmentId')->willReturn(null);
        $incoming->method('getData')->willReturn(['name' => 'x']);

        $workModel = $this->segmentModel(['getId', 'addData']);
        $this->segmentFactory->method('create')->willReturn($workModel);
        $this->resource->method('save')->willThrowException(new \Exception('db down'));

        $this->expectException(CouldNotSaveException::class);
        $this->repository->save($incoming);
    }

    #[Test]
    public function getByIdReturnsLoadedModel(): void
    {
        $model = $this->segmentModel(['getId']);
        $model->method('getId')->willReturn(9);
        $this->segmentFactory->method('create')->willReturn($model);
        $this->resource->expects($this->once())->method('load')->with($model, 9);

        $this->assertSame($model, $this->repository->getById(9));
    }

    #[Test]
    public function getByIdThrowsWhenMissing(): void
    {
        $model = $this->segmentModel(['getId']);
        $model->method('getId')->willReturn(null);
        $this->segmentFactory->method('create')->willReturn($model);

        $this->expectException(NoSuchEntityException::class);
        $this->repository->getById(404);
    }

    #[Test]
    public function deleteRemovesLoadedModel(): void
    {
        $dto = $this->segmentModel(['getSegmentId']);
        $dto->method('getSegmentId')->willReturn(6);

        $model = $this->segmentModel(['getId']);
        $model->method('getId')->willReturn(6);
        $this->segmentFactory->method('create')->willReturn($model);
        $this->resource->expects($this->once())->method('delete')->with($model);

        $this->assertTrue($this->repository->delete($dto));
    }

    #[Test]
    public function deleteThrowsWhenMissing(): void
    {
        $dto = $this->segmentModel(['getSegmentId']);
        $dto->method('getSegmentId')->willReturn(6);

        $model = $this->segmentModel(['getId']);
        $model->method('getId')->willReturn(null);
        $this->segmentFactory->method('create')->willReturn($model);
        $this->resource->expects($this->never())->method('delete');

        $this->expectException(NoSuchEntityException::class);
        $this->repository->delete($dto);
    }

    #[Test]
    public function getListBuildsSearchResults(): void
    {
        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $itemModel = $this->segmentModel(['getId']);
        $collection = $this->createMock(SegmentCollection::class);
        $collection->method('getSize')->willReturn(1);
        $collection->method('getItems')->willReturn([$itemModel]);
        $this->collectionFactory->method('create')->willReturn($collection);
        $this->collectionProcessor->expects($this->once())
            ->method('process')->with($searchCriteria, $collection);

        // convertToDataModel round-trips through the processor + helper.
        $this->dataObjectProcessor->method('buildOutputDataArray')
            ->willReturn(['segment_id' => 1, 'name' => 'A']);
        $dto = $this->segmentModel([]);
        $this->segmentFactory->method('create')->willReturn($dto);

        $searchResults = $this->createMock(SegmentSearchResultsInterface::class);
        $searchResults->expects($this->once())->method('setSearchCriteria')->with($searchCriteria);
        $searchResults->expects($this->once())->method('setTotalCount')->with(1);
        $searchResults->expects($this->once())->method('setItems')->with([$dto]);
        $this->searchResultsFactory->method('create')->willReturn($searchResults);

        $this->assertSame($searchResults, $this->repository->getList($searchCriteria));
    }
}
