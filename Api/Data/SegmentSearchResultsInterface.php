<?php
/**
 * Magendoo CustomerSegment Segment Search Results Interface
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface for segment search results
 *
 * @api
 */
interface SegmentSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get segments list
     *
     * @return SegmentInterface[]
     */
    public function getItems(): array;

    /**
     * Set segments list
     *
     * @param SegmentInterface[] $items
     * @return $this
     */
    public function setItems(array $items): static;
}
