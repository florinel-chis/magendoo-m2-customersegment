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
use Magento\Framework\Registry;
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
     * @var Registry
     */
    protected Registry $coreRegistry;

    /**
     * @var SegmentFactory
     */
    protected SegmentFactory $segmentFactory;

    /**
     * @param Context $context
     * @param SegmentRepositoryInterface $segmentRepository
     * @param Registry $coreRegistry
     * @param SegmentFactory $segmentFactory
     */
    public function __construct(
        Context $context,
        SegmentRepositoryInterface $segmentRepository,
        Registry $coreRegistry,
        SegmentFactory $segmentFactory
    ) {
        $this->segmentRepository = $segmentRepository;
        $this->coreRegistry = $coreRegistry;
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

        // Register segment for conditions block
        $this->coreRegistry->register('current_segment', $segment);

        // Configure conditions form for editing (like SalesRule does)
        if ($segment->getId()) {
            $formName = 'customersegment_segment_form';
            if ($segment->getConditions()) {
                $segment->getConditions()->setFormName($formName);
                $segment->getConditions()->setJsFormObject(
                    $segment->getConditionsFieldSetId($formName)
                );
            }
        }

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Magendoo_CustomerSegment::segments');
        $resultPage->addBreadcrumb(__('Customer Segments'), __('Customer Segments'));
        $resultPage->addBreadcrumb($pageTitle, $pageTitle);
        $resultPage->getConfig()->getTitle()->prepend($pageTitle);

        return $resultPage;
    }
}
