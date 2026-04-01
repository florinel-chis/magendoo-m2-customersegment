<?php
/**
 * Magendoo CustomerSegment Delete Button
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Block\Adminhtml\Segment\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Delete button for segment edit form
 */
class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritdoc
     */
    public function getButtonData(): array
    {
        $data = [];
        $segmentId = $this->getSegmentId();
        if ($segmentId) {
            $data = [
                'label' => __('Delete'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\'' . __('Are you sure you want to delete this segment?') . '\', \'' . $this->getDeleteUrl() . '\')',
                'sort_order' => 20,
            ];
        }
        return $data;
    }

    /**
     * Get delete URL
     *
     * @return string
     */
    public function getDeleteUrl(): string
    {
        return $this->getUrl('*/*/delete', ['segment_id' => $this->getSegmentId()]);
    }
}
