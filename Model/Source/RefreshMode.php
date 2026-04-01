<?php
/**
 * Magendoo CustomerSegment Refresh Mode Source Model
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magendoo\CustomerSegment\Api\Data\SegmentInterface;

/**
 * Refresh Mode source model
 */
class RefreshMode implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => SegmentInterface::REFRESH_MODE_MANUAL,
                'label' => __('Manual')
            ],
            [
                'value' => SegmentInterface::REFRESH_MODE_CRON,
                'label' => __('Cron Schedule')
            ],
            [
                'value' => SegmentInterface::REFRESH_MODE_REALTIME,
                'label' => __('Real-time')
            ]
        ];
    }

    /**
     * Get options as array (value => label)
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            SegmentInterface::REFRESH_MODE_MANUAL => __('Manual'),
            SegmentInterface::REFRESH_MODE_CRON => __('Cron Schedule'),
            SegmentInterface::REFRESH_MODE_REALTIME => __('Real-time')
        ];
    }
}
