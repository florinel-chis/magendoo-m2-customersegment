<?php
/**
 * Magendoo CustomerSegment Matched Customers Data Provider
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Ui\DataProvider\Form;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderInterface;
use Magendoo\CustomerSegment\Model\ResourceModel\Customer\CollectionFactory as SegmentCustomerCollectionFactory;

class MatchedCustomersDataProvider implements DataProviderInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $primaryFieldName;

    /**
     * @var string
     */
    protected $requestFieldName;

    /**
     * @var array
     */
    protected $meta = [];

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var SegmentCustomerCollectionFactory
     */
    private $segmentCustomerCollectionFactory;

    /**
     * @var CollectionFactory
     */
    private $customerCollectionFactory;

    /**
     * @var int|null
     */
    private $segmentId;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $customerCollectionFactory
     * @param SegmentCustomerCollectionFactory $segmentCustomerCollectionFactory
     * @param RequestInterface $request
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $customerCollectionFactory,
        SegmentCustomerCollectionFactory $segmentCustomerCollectionFactory,
        RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->name = $name;
        $this->primaryFieldName = $primaryFieldName;
        $this->requestFieldName = $requestFieldName;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->segmentCustomerCollectionFactory = $segmentCustomerCollectionFactory;
        $this->request = $request;
        $this->meta = $meta;
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getPrimaryFieldName(): string
    {
        return $this->primaryFieldName;
    }

    /**
     * @inheritdoc
     */
    public function getRequestFieldName(): string
    {
        return $this->requestFieldName;
    }

    /**
     * @inheritdoc
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @inheritdoc
     */
    public function getFieldMetaInfo($fieldSetName, $fieldName): array
    {
        return $this->meta[$fieldSetName]['children'][$fieldName] ?? [];
    }

    /**
     * @inheritdoc
     */
    public function getFieldSetMetaInfo($fieldSetName): array
    {
        return $this->meta[$fieldSetName] ?? [];
    }

    /**
     * @inheritdoc
     */
    public function getFieldsMetaInfo($fieldSetName): array
    {
        return $this->meta[$fieldSetName]['children'] ?? [];
    }

    /**
     * @inheritdoc
     */
    public function getData(): array
    {
        $segmentId = $this->getSegmentId();

        if (!$segmentId) {
            return [
                'items' => [],
                'totalRecords' => 0
            ];
        }

        $customerIds = $this->getSegmentCustomerIds($segmentId);

        if (empty($customerIds)) {
            return [
                'items' => [],
                'totalRecords' => 0
            ];
        }

        $collection = $this->customerCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['in' => $customerIds]);
        $collection->addNameToSelect();
        $collection->addAttributeToSelect('email');
        $collection->joinAttribute('billing_postcode', 'customer_address/postcode', 'default_billing', null, 'left')
            ->joinAttribute('billing_city', 'customer_address/city', 'default_billing', null, 'left')
            ->joinAttribute('billing_region', 'customer_address/region', 'default_billing', null, 'left')
            ->joinAttribute('billing_country_id', 'customer_address/country_id', 'default_billing', null, 'left');

        // Apply pagination
        $requestData = $this->request->getParams();
        $pageSize = $requestData['paging']['pageSize'] ?? 20;
        $currentPage = $requestData['paging']['current'] ?? 1;

        $collection->setPageSize($pageSize);
        $collection->setCurPage($currentPage);

        $items = [];
        foreach ($collection as $customer) {
            $items[] = [
                'customer_id' => $customer->getId(),
                'name' => $customer->getName(),
                'email' => $customer->getEmail(),
                'billing_city' => $customer->getBillingCity(),
                'billing_region' => $customer->getBillingRegion(),
                'billing_country_id' => $customer->getBillingCountryId(),
                'created_at' => $customer->getCreatedAt(),
            ];
        }

        return [
            'items' => $items,
            'totalRecords' => $collection->getSize()
        ];
    }

    /**
     * @inheritdoc
     */
    public function addFilter(\Magento\Framework\Api\Filter $filter): void
    {
        // Filters not implemented for this provider
    }

    /**
     * @inheritdoc
     */
    public function addOrder($field, $direction): void
    {
        // Sorting not implemented for this provider
    }

    /**
     * @inheritdoc
     */
    public function setLimit($offset, $size): void
    {
        // Limit handled in getData
    }

    /**
     * @inheritdoc
     */
    public function getConfigData(): array
    {
        return $this->data['config'] ?? [];
    }

    /**
     * @inheritdoc
     */
    public function setConfigData($config): void
    {
        $this->data['config'] = $config;
    }

    /**
     * @inheritdoc
     */
    public function getSearchCriteria(): \Magento\Framework\Api\Search\SearchCriteriaInterface
    {
        throw new \RuntimeException('Not implemented');
    }

    /**
     * @inheritdoc
     */
    public function getSearchResult(): \Magento\Framework\Api\Search\SearchResultInterface
    {
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Get customer IDs for a segment
     *
     * @param int $segmentId
     * @return array
     */
    private function getSegmentCustomerIds(int $segmentId): array
    {
        $collection = $this->segmentCustomerCollectionFactory->create();
        $collection->addFieldToFilter('segment_id', $segmentId);
        $collection->addFieldToSelect('customer_id');

        $customerIds = [];
        foreach ($collection as $item) {
            $customerIds[] = (int) $item->getCustomerId();
        }

        return $customerIds;
    }

    /**
     * Get current segment ID from request
     *
     * @return int|null
     */
    private function getSegmentId(): ?int
    {
        if ($this->segmentId === null) {
            $this->segmentId = (int) $this->request->getParam('segment_id') ?: null;
        }
        return $this->segmentId;
    }
}
