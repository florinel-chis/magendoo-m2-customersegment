<?php
/**
 * Magendoo CustomerSegment Cart Price Rule Condition
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Rule\Condition;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Rule\Model\Condition\Context;
use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;

/**
 * Customer Segment condition for Cart Price Rules
 */
class Segment extends AbstractCondition
{
    /**
     * @var SegmentManagementInterface
     */
    protected SegmentManagementInterface $segmentManagement;

    /**
     * @var SegmentRepositoryInterface
     */
    protected SegmentRepositoryInterface $segmentRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @param Context $context
     * @param SegmentManagementInterface $segmentManagement
     * @param SegmentRepositoryInterface $segmentRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param array $data
     */
    public function __construct(
        Context $context,
        SegmentManagementInterface $segmentManagement,
        SegmentRepositoryInterface $segmentRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        array $data = []
    ) {
        $this->segmentManagement = $segmentManagement;
        $this->segmentRepository = $segmentRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        parent::__construct($context, $data);
    }

    /**
     * Load attribute options
     *
     * @return $this
     */
    public function loadAttributeOptions(): static
    {
        $this->setAttributeOption([
            'segment_id' => __('Customer Segment'),
        ]);

        return $this;
    }

    /**
     * Get input type
     *
     * @return string
     */
    public function getInputType(): string
    {
        return 'multiselect';
    }

    /**
     * Get value element type
     *
     * @return string
     */
    public function getValueElementType(): string
    {
        return 'multiselect';
    }

    /**
     * Get value select options
     *
     * @return array
     */
    public function getValueSelectOptions(): array
    {
        try {
            $this->searchCriteriaBuilder->addFilter('is_active', 1);
            $searchCriteria = $this->searchCriteriaBuilder->create();
            
            $searchResults = $this->segmentRepository->getList($searchCriteria);
            $segments = $searchResults->getItems();
            
            $options = [];
            foreach ($segments as $segment) {
                $options[] = [
                    'label' => $segment->getName(),
                    'value' => $segment->getSegmentId(),
                ];
            }
            
            return $options;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Validate if customer is in segment
     *
     * @param \Magento\Quote\Model\Quote\Address|\Magento\Quote\Model\Quote $object
     * @return bool
     */
    public function validate($object): bool
    {
        $quote = $object->getQuote() ?? $object;
        $customerId = $quote->getCustomerId();

        if (!$customerId) {
            return false;
        }

        $segmentIds = $this->getValue();
        if (!is_array($segmentIds)) {
            $segmentIds = explode(',', $segmentIds);
        }

        foreach ($segmentIds as $segmentId) {
            if ($this->segmentManagement->isCustomerInSegment((int) $customerId, (int) $segmentId)) {
                return true;
            }
        }

        return false;
    }
}
