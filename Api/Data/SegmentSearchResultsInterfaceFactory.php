<?php
/**
 * Magendoo CustomerSegment Segment Search Results Interface Factory
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Api\Data;

use Magento\Framework\Api\SearchResults;
use Magento\Framework\ObjectManagerInterface;

/**
 * Factory class for SegmentSearchResultsInterface
 */
class SegmentSearchResultsInterfaceFactory
{
    /**
     * Object Manager instance
     *
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * Factory constructor
     *
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create class instance with specified parameters
     *
     * @param array $data
     * @return SegmentSearchResultsInterface
     */
    public function create(array $data = []): SegmentSearchResultsInterface
    {
        // Use the preference defined in di.xml
        return $this->objectManager->create(SegmentSearchResultsInterface::class, $data);
    }
}
