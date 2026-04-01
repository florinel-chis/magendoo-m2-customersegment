<?php
/**
 * Magendoo CustomerSegment Segment Refresh Controller
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
use Magendoo\CustomerSegment\Api\SegmentManagementInterface;

/**
 * Refresh segment controller
 */
class Refresh extends Action implements HttpPostActionInterface
{
    /**
     * Authorization resource
     */
    public const ADMIN_RESOURCE = 'Magendoo_CustomerSegment::segment_refresh';

    /**
     * @var SegmentManagementInterface
     */
    protected SegmentManagementInterface $segmentManagement;

    /**
     * @param Context $context
     * @param SegmentManagementInterface $segmentManagement
     */
    public function __construct(
        Context $context,
        SegmentManagementInterface $segmentManagement
    ) {
        $this->segmentManagement = $segmentManagement;
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
            $this->messageManager->addErrorMessage(__('We can\'t find a segment to refresh.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $customerCount = $this->segmentManagement->refreshSegment($segmentId);
            $this->messageManager->addSuccessMessage(
                __('Segment has been refreshed. %1 customers matched.', $customerCount)
            );
            
            // Redirect back to edit page if refresh was from there
            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['segment_id' => $segmentId]);
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
