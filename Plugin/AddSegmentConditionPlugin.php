<?php
/**
 * Magendoo CustomerSegment - Cart Price Rule condition registration
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Plugin;

use Magento\SalesRule\Model\Rule\Condition\Combine;

/**
 * Exposes the customer segment condition inside Cart Price Rule conditions.
 *
 * The condition group is offered unconditionally so that rules can be authored
 * before any segment exists; the concrete segment list is supplied by
 * Model\Rule\Condition\Segment::getValueSelectOptions() when the option is used.
 */
class AddSegmentConditionPlugin
{
    /**
     * Add the customer segment condition to the available conditions.
     *
     * @param Combine $subject
     * @param array $result
     * @return array
     */
    public function afterGetNewChildSelectOptions(
        Combine $subject,
        array $result
    ): array {
        $result[] = [
            'label' => __('Customer Segments'),
            'value' => [
                [
                    'label' => __('Segment'),
                    'value' => \Magendoo\CustomerSegment\Model\Rule\Condition\Segment::class,
                ],
            ],
        ];

        return $result;
    }
}
