<?php
/**
 * Magendoo CustomerSegment Segment Edit Controller
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
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magendoo\CustomerSegment\Model\SegmentFactory;

/**
 * Edit segment controller
 */
class Edit extends Action implements HttpGetActionInterface
{
    /**
     * Authorization resource
     */
    public const ADMIN_RESOURCE = 'Magendoo_CustomerSegment::segment_edit';

    /**
     * @var SegmentRepositoryInterface
     */
    protected SegmentRepositoryInterface $segmentRepository;

    /**
     * @var DataPersistorInterface
     */
    protected DataPersistorInterface $dataPersistor;

    /**
     * @var SegmentFactory
     */
    protected SegmentFactory $segmentFactory;

    /**
     * @param Context $context
     * @param SegmentRepositoryInterface $segmentRepository
     * @param DataPersistorInterface $dataPersistor
     * @param SegmentFactory $segmentFactory
     */
    public function __construct(
        Context $context,
        SegmentRepositoryInterface $segmentRepository,
        DataPersistorInterface $dataPersistor,
        SegmentFactory $segmentFactory
    ) {
        $this->segmentRepository = $segmentRepository;
        $this->dataPersistor = $dataPersistor;
        $this->segmentFactory = $segmentFactory;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $segmentId = (int) $this->getRequest()->getParam('segment_id');

        if ($segmentId) {
            try {
                $segment = $this->segmentRepository->getById($segmentId);
                $pageTitle = __('Edit Segment: %1', $segment->getName());
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This segment no longer exists.'));
                /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                return $resultRedirect->setPath('*/*/');
            }
        } else {
            $segment = $this->segmentFactory->create();
            $pageTitle = __('New Segment');
        }

        // Store only the segment ID in DataPersistor (not the model object,
        // which contains non-serializable dependencies like FormFactory/ObjectManager
        // that cause "Serialization of Closure" fatal errors on session_write_close)
        $this->dataPersistor->set('current_segment_id', $segment->getId());

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Magendoo_CustomerSegment::segments');
        $resultPage->addBreadcrumb(__('Customer Segments'), __('Customer Segments'));
        $resultPage->addBreadcrumb($pageTitle, $pageTitle);
        $resultPage->getConfig()->getTitle()->prepend($pageTitle);

        return $resultPage;
    }
}
