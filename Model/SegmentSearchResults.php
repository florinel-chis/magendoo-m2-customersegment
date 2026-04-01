<?php
/**
 * Magendoo CustomerSegment Segment Search Results
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
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
