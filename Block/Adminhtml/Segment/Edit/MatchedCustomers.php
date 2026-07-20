<?php
/**
 * Magendoo CustomerSegment - matched customers block
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
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
     * Maximum number of matched customers rendered in the preview list.
     */
    public const MAX_DISPLAY = 50;

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
     * Cached rendered rows.
     *
     * @var array|null
     */
    private $customers;

    /**
     * Cached total matched-customer count.
     *
     * @var int|null
     */
    private $totalCount;

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
     * Get the total number of customers matched by this segment
     *
     * @return int
     */
    public function getCustomerCount(): int
    {
        if ($this->totalCount === null) {
            $this->getCustomers();
        }

        return (int) ($this->totalCount ?? 0);
    }

    /**
     * Get the number of matched customers actually rendered in the preview
     *
     * @return int
     */
    public function getShownCount(): int
    {
        return count($this->getCustomers());
    }

    /**
     * Whether the preview list is capped below the real total
     *
     * @return bool
     */
    public function isCapped(): bool
    {
        return $this->getCustomerCount() > $this->getShownCount();
    }

    /**
     * Maximum number of rows shown in the preview list
     *
     * @return int
     */
    public function getMaxDisplay(): int
    {
        return self::MAX_DISPLAY;
    }

    /**
     * Get a bounded list of customers matched by this segment
     *
     * Runs one COUNT (for the total) and one bounded SELECT of the customer ids,
     * then a single customer collection load — no duplicate size queries.
     *
     * @return array
     */
    public function getCustomers(): array
    {
        if ($this->customers !== null) {
            return $this->customers;
        }

        $this->totalCount = 0;
        $this->customers = [];

        if (!$this->hasSegmentId()) {
            return $this->customers;
        }

        $linkCollection = $this->segmentCustomerCollectionFactory->create();
        $linkCollection->addFieldToFilter('segment_id', $this->getSegmentId());
        $linkCollection->addFieldToSelect('customer_id');
        $linkCollection->setPageSize(self::MAX_DISPLAY);
        $linkCollection->setCurPage(1);

        $this->totalCount = (int) $linkCollection->getSize();

        $customerIds = [];
        foreach ($linkCollection as $item) {
            $customerIds[] = (int) $item->getCustomerId();
        }

        if (empty($customerIds)) {
            return $this->customers;
        }

        $collection = $this->customerCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['in' => $customerIds]);
        $collection->addNameToSelect();
        $collection->addAttributeToSelect(['email', 'created_at']);
        $collection->joinAttribute('billing_city', 'customer_address/city', 'default_billing', null, 'left')
            ->joinAttribute('billing_region', 'customer_address/region', 'default_billing', null, 'left')
            ->joinAttribute('billing_country_id', 'customer_address/country_id', 'default_billing', null, 'left');

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

        $this->customers = $customers;

        return $this->customers;
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
