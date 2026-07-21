<?php
/**
 * Magendoo CustomerSegment Segment Search Results Interface
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
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
     * @return \Magendoo\CustomerSegment\Api\Data\SegmentInterface[]
     */
    public function getItems(): array;

    /**
     * Set segments list
     *
     * @param \Magendoo\CustomerSegment\Api\Data\SegmentInterface[] $items
     * @return $this
     */
    public function setItems(array $items): static;
}
