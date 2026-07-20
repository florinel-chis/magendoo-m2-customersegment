<?php
/**
 * Magendoo CustomerSegment Segment Search Results
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model;

use Magento\Framework\Api\SearchResults;
use Magendoo\CustomerSegment\Api\Data\SegmentSearchResultsInterface;

/**
 * Segment Search Results
 */
class SegmentSearchResults extends SearchResults implements SegmentSearchResultsInterface
{
    /**
     * @inheritdoc
     */
    public function getItems(): array
    {
        return parent::getItems();
    }

    /**
     * @inheritdoc
     */
    public function setItems(array $items): static
    {
        return parent::setItems($items);
    }
}
