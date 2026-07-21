<?php
/**
 * Magendoo CustomerSegment Status Source Model
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Status source model
 */
class Status implements OptionSourceInterface
{
    /**
     * Status values
     */
    public const ENABLED = 1;
    public const DISABLED = 0;

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::ENABLED,
                'label' => __('Active')
            ],
            [
                'value' => self::DISABLED,
                'label' => __('Inactive')
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
            self::ENABLED => __('Active'),
            self::DISABLED => __('Inactive')
        ];
    }
}
