<?php
/**
 * Magendoo CustomerSegment Matched Customers Block
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Block\Adminhtml\Segment\Edit;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magendoo\CustomerSegment\Model\ResourceModel\Customer\CollectionFactory as SegmentCustomerCollectionFactory;

class MatchedCustomers extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Magendoo_CustomerSegment::segment/edit/matched_customers.phtml';

    /**
     * @var CollectionFactory
     */
    private $customerCollectionFactory;

    /**
     * @var SegmentCustomerCollectionFactory
     */
    private $segmentCustomerCollectionFactory;

    /**
     * @param Context $context
     * @param CollectionFactory $customerCollectionFactory
     * @param SegmentCustomerCollectionFactory $segmentCustomerCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $customerCollectionFactory,
        SegmentCustomerCollectionFactory $segmentCustomerCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->segmentCustomerCollectionFactory = $segmentCustomerCollectionFactory;
    }

    /**
     * Check if we have a segment ID
     *
     * @return bool
     */
    public function hasSegmentId(): bool
    {
        return (bool) $this->getSegmentId();
    }

    /**
     * Get current segment ID
     *
     * @return int|null
     */
    public function getSegmentId(): ?int
    {
        $request = $this->getRequest();
        return (int) $request->getParam('segment_id') ?: null;
    }

    /**
     * Get customer count for this segment
     *
     * @return int
     */
    public function getCustomerCount(): int
    {
        if (!$this->hasSegmentId()) {
            return 0;
        }

        $collection = $this->segmentCustomerCollectionFactory->create();
        $collection->addFieldToFilter('segment_id', $this->getSegmentId());

        return (int) $collection->getSize();
    }

    /**
     * Get customers for this segment
     *
     * @param int $pageSize
     * @param int $currentPage
     * @return array
     */
    public function getCustomers(int $pageSize = 20, int $currentPage = 1): array
    {
        if (!$this->hasSegmentId()) {
            return [];
        }

        $customerIds = $this->getSegmentCustomerIds();

        if (empty($customerIds)) {
            return [];
        }

        $collection = $this->customerCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['in' => $customerIds]);
        $collection->addNameToSelect();
        $collection->addAttributeToSelect(['email', 'created_at']);
        $collection->joinAttribute('billing_postcode', 'customer_address/postcode', 'default_billing', null, 'left')
            ->joinAttribute('billing_city', 'customer_address/city', 'default_billing', null, 'left')
            ->joinAttribute('billing_region', 'customer_address/region', 'default_billing', null, 'left')
            ->joinAttribute('billing_country_id', 'customer_address/country_id', 'default_billing', null, 'left');

        $collection->setPageSize($pageSize);
        $collection->setCurPage($currentPage);

        $customers = [];
        foreach ($collection as $customer) {
            $customers[] = [
                'id' => $customer->getId(),
                'name' => $customer->getName(),
                'email' => $customer->getEmail(),
                'city' => $customer->getBillingCity(),
                'region' => $customer->getBillingRegion(),
                'country' => $customer->getBillingCountryId(),
                'created_at' => $customer->getCreatedAt(),
            ];
        }

        return $customers;
    }

    /**
     * Get customer IDs for this segment
     *
     * @return array
     */
    private function getSegmentCustomerIds(): array
    {
        $collection = $this->segmentCustomerCollectionFactory->create();
        $collection->addFieldToFilter('segment_id', $this->getSegmentId());
        $collection->addFieldToSelect('customer_id');

        $customerIds = [];
        foreach ($collection as $item) {
            $customerIds[] = (int) $item->getCustomerId();
        }

        return $customerIds;
    }

    /**
     * Get customer view URL
     *
     * @param int $customerId
     * @return string
     */
    public function getCustomerUrl(int $customerId): string
    {
        return $this->getUrl('customer/index/edit', ['id' => $customerId]);
    }
}
