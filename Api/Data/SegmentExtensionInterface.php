<?php
/**
 * Magendoo CustomerSegment Segment Extension Interface
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Api\Data;

/**
 * Segment Extension Interface for extension attributes
 *
 * @api
 */
interface SegmentExtensionInterface extends \Magento\Framework\Api\ExtensionAttributesInterface
{
    // This interface is intentionally empty as it serves as a marker
    // for extension attributes. Third-party modules can extend this
    // interface to add custom extension attributes.
}
