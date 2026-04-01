<?php
/**
 * Magendoo CustomerSegment Generic Button
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Block\Adminhtml\Segment\Edit;

use Magento\Backend\Block\Widget\Context;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Generic button base class
 */
class GenericButton
{
    /**
     * @var Context
     */
    protected Context $context;

    /**
     * @var SegmentRepositoryInterface
     */
    protected SegmentRepositoryInterface $segmentRepository;

    /**
     * @param Context $context
     * @param SegmentRepositoryInterface $segmentRepository
     */
    public function __construct(
        Context $context,
        SegmentRepositoryInterface $segmentRepository
    ) {
        $this->context = $context;
        $this->segmentRepository = $segmentRepository;
    }

    /**
     * Return segment ID
     *
     * @return int|null
     */
    public function getSegmentId(): ?int
    {
        $segmentId = $this->context->getRequest()->getParam('segment_id');
        
        // Return null for new entity creation (no ID in request)
        if (!$segmentId) {
            return null;
        }
        
        try {
            $id = $this->segmentRepository->getById((int) $segmentId)->getId();
            return $id ? (int) $id : null;
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Generate url by route and parameters
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
