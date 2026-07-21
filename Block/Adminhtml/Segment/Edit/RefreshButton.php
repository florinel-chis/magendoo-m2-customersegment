<?php
/**
 * Magendoo CustomerSegment Refresh Button
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Block\Adminhtml\Segment\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class RefreshButton implements ButtonProviderInterface
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @param Context $context
     */
    public function __construct(
        Context $context
    ) {
        $this->context = $context;
    }

    /**
     * Get button data
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $segmentId = $this->getSegmentId();

        if (!$segmentId) {
            return [];
        }

        return [
            'label' => __('Refresh'),
            'class' => 'action-secondary',
            'on_click' => sprintf(
                "location.href = '%s';",
                $this->context->getUrlBuilder()->getUrl(
                    'customersegment/segment/refresh',
                    ['segment_id' => $segmentId]
                )
            ),
            'sort_order' => 30,
        ];
    }

    /**
     * Get current segment ID
     *
     * @return int|null
     */
    private function getSegmentId(): ?int
    {
        return (int) $this->context->getRequest()->getParam('segment_id') ?: null;
    }
}
