<?php
/**
 * Magendoo CustomerSegment Mass Delete Controller
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
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment\CollectionFactory;

/**
 * Mass delete controller
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    /**
     * Authorization resource
     */
    public const ADMIN_RESOURCE = 'Magendoo_CustomerSegment::segment_delete';

    /**
     * @var Filter
     */
    protected Filter $filter;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $collectionFactory;

    /**
     * @var SegmentRepositoryInterface
     */
    protected SegmentRepositoryInterface $segmentRepository;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param SegmentRepositoryInterface $segmentRepository
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        SegmentRepositoryInterface $segmentRepository
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
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
        
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $segmentDeleted = 0;

            foreach ($collection->getAllIds() as $segmentId) {
                $this->segmentRepository->deleteById((int) $segmentId);
                $segmentDeleted++;
            }

            if ($segmentDeleted) {
                $this->messageManager->addSuccessMessage(
                    __('A total of %1 segment(s) have been deleted.', $segmentDeleted)
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
