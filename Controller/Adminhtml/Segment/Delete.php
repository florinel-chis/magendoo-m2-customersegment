<?php
/**
 * Magendoo CustomerSegment Segment Delete Controller
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Controller\Adminhtml\Segment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;

/**
 * Delete segment controller
 */
class Delete extends Action implements HttpPostActionInterface
{
    /**
     * Authorization resource
     */
    public const ADMIN_RESOURCE = 'Magendoo_CustomerSegment::segment_delete';

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
        $this->segmentRepository = $segmentRepository;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        
        $segmentId = (int) $this->getRequest()->getParam('segment_id');
        
        if (!$segmentId) {
            $this->messageManager->addErrorMessage(__('We can\'t find a segment to delete.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->segmentRepository->deleteById($segmentId);
            $this->messageManager->addSuccessMessage(__('The segment has been deleted.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
