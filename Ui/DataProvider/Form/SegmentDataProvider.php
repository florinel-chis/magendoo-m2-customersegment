<?php
/**
 * Magendoo CustomerSegment - segment form data provider
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Ui\DataProvider\Form;

use Magendoo\CustomerSegment\Model\ResourceModel\Segment\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\Modifier\PoolInterface;
use Magento\Ui\DataProvider\ModifierPoolDataProvider;

/**
 * Form Data Provider for Customer Segments
 */
class SegmentDataProvider extends ModifierPoolDataProvider
{
    /**
     * @var \Magendoo\CustomerSegment\Model\ResourceModel\Segment\Collection
     */
    protected $collection;

    /**
     * @var DataPersistorInterface
     */
    protected DataPersistorInterface $dataPersistor;

    /**
     * @var array
     */
    protected array $loadedData = [];

    /**
     * Constructor
     *
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $segmentCollectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param array $meta
     * @param array $data
     * @param PoolInterface|null $pool
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $segmentCollectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = [],
        ?PoolInterface $pool = null
    ) {
        $this->collection = $segmentCollectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data, $pool);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $segment) {
            /** @var \Magendoo\CustomerSegment\Model\Segment $segment */
            $data = $segment->getData();

            // Format conditions for the form
            $conditionsArray = $segment->getConditionsArray();
            if ($conditionsArray) {
                $data['rule']['conditions'] = $conditionsArray;
            }

            $this->loadedData[$segment->getId()] = $data;
        }

        $data = $this->dataPersistor->get('customersegment_segment');
        if (!empty($data)) {
            /** @var \Magendoo\CustomerSegment\Model\Segment $segment */
            $segment = $this->collection->getNewEmptyItem();
            $segment->setData($data);

            $loadedItem = $segment->getData();
            // Format conditions for the form
            $conditionsArray = $segment->getConditionsArray();
            if ($conditionsArray) {
                $loadedItem['rule']['conditions'] = $conditionsArray;
            }

            $this->loadedData[$segment->getId()] = $loadedItem;
            $this->dataPersistor->clear('customersegment_segment');
        }

        return $this->loadedData;
    }
}
