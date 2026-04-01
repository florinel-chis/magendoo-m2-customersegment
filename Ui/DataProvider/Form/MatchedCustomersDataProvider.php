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
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as BaseDataProvider;
use Magendoo\CustomerSegment\Model\ResourceModel\Customer\CollectionFactory as SegmentCustomerCollectionFactory;

class MatchedCustomersDataProvider extends BaseDataProvider
{
    /**
     * @var RequestInterface
     */
    private $request;

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
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->segmentCustomerCollectionFactory = $segmentCustomerCollectionFactory;
        $this->request = $request;
    }

    /**
     * Get data
     *
     * @return array
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
