<?php
/**
 * Magendoo CustomerSegment Mass Refresh Controller
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
use Magento\Ui\Component\MassAction\Filter;
use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment\CollectionFactory;

/**
 * Mass refresh controller
 */
class MassRefresh extends Action implements HttpPostActionInterface
{
    /**
     * Authorization resource
     */
    public const ADMIN_RESOURCE = 'Magendoo_CustomerSegment::segment_refresh';

    /**
     * @var Filter
     */
    protected Filter $filter;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $collectionFactory;

    /**
     * @var SegmentManagementInterface
     */
    protected SegmentManagementInterface $segmentManagement;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param SegmentManagementInterface $segmentManagement
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        SegmentManagementInterface $segmentManagement
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
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
        
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $segmentIds = $collection->getAllIds();
            
            if (empty($segmentIds)) {
                $this->messageManager->addWarningMessage(__('No segments selected.'));
                return $resultRedirect->setPath('*/*/');
            }

            $totalCustomers = $this->segmentManagement->massRefresh($segmentIds);

            $this->messageManager->addSuccessMessage(
                __('%1 segment(s) have been refreshed. Total customers matched: %2', 
                    count($segmentIds), 
                    $totalCustomers
                )
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
