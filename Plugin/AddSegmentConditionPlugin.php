<?php
/**
 * Magendoo CustomerSegment Add Segment Condition Plugin
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Plugin;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;

/**
 * Plugin to add customer segment condition to Cart Price Rules
 */
class AddSegmentConditionPlugin
{
    /**
     * @var SegmentRepositoryInterface
     */
    protected SegmentRepositoryInterface $segmentRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @param SegmentRepositoryInterface $segmentRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        SegmentRepositoryInterface $segmentRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->segmentRepository = $segmentRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Add customer segment condition to available conditions
     *
     * @param \Magento\SalesRule\Model\Rule\Condition\Combine $subject
     * @param array $result
     * @return array
     */
    public function afterGetNewChildSelectOptions(
        \Magento\SalesRule\Model\Rule\Condition\Combine $subject,
        array $result
    ): array {
        // Get active segments for the dropdown
        $segmentOptions = $this->getSegmentOptions();

        if (!empty($segmentOptions)) {
            $result[] = [
                'label' => __('Customer Segments'),
                'value' => [
                    [
                        'label' => __('Segment'),
                        'value' => 'Magendoo\CustomerSegment\Model\Rule\Condition\Segment',
                    ],
                ],
            ];
        }

        return $result;
    }

    /**
     * Get segment options for dropdown
     *
     * @return array
     */
    protected function getSegmentOptions(): array
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
}
