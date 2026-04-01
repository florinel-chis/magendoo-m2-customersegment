<?php
/**
 * Magendoo CustomerSegment Segment Save Controller
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
use Magento\Framework\Exception\LocalizedException;
use Magendoo\CustomerSegment\Api\Data\SegmentInterface;
use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magendoo\CustomerSegment\Model\SegmentFactory;

/**
 * Save segment controller
 */
class Save extends Action implements HttpPostActionInterface
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
     * @var SegmentManagementInterface
     */
    protected SegmentManagementInterface $segmentManagement;

    /**
     * @var SegmentFactory
     */
    protected SegmentFactory $segmentFactory;

    /**
     * @param Context $context
     * @param SegmentRepositoryInterface $segmentRepository
     * @param SegmentManagementInterface $segmentManagement
     * @param SegmentFactory $segmentFactory
     */
    public function __construct(
        Context $context,
        SegmentRepositoryInterface $segmentRepository,
        SegmentManagementInterface $segmentManagement,
        SegmentFactory $segmentFactory
    ) {
        $this->segmentRepository = $segmentRepository;
        $this->segmentManagement = $segmentManagement;
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
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $segmentId = isset($data['segment_id']) ? (int) $data['segment_id'] : null;

        try {
            if ($segmentId) {
                $segment = $this->segmentRepository->getById($segmentId);
            } else {
                $segment = $this->createSegmentModel();
            }

            // Populate segment data
            $segment->setName($data['name'] ?? '');
            $segment->setDescription($data['description'] ?? '');
            $segment->setIsActive(isset($data['is_active']) && $data['is_active'] === '1');
            $segment->setRefreshMode($data['refresh_mode'] ?? SegmentInterface::REFRESH_MODE_MANUAL);
            $segment->setCronExpression($data['cron_expression'] ?? null);

            // Handle conditions - convert from form format to array
            if (isset($data['rule']['conditions'])) {
                $data['conditions'] = $data['rule']['conditions'];
                unset($data['rule']);
            }

            // Use loadPost to handle conditions like Magento rules
            $segment->loadPost($data);

            $segment = $this->segmentRepository->save($segment);

            $this->messageManager->addSuccessMessage(__('The segment has been saved.'));

            // Refresh segment if auto-refresh is enabled or requested
            if (!empty($data['refresh_after_save'])) {
                $this->segmentManagement->refreshSegment($segment->getSegmentId());
                $this->messageManager->addSuccessMessage(__('Segment data has been refreshed.'));
            }

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['segment_id' => $segment->getSegmentId()]);
            }

            return $resultRedirect->setPath('*/*/');

        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the segment.'));
        }

        // Redirect back with data
        $redirectParams = ['_current' => true, '_use_forward' => false];
        if ($segmentId) {
            $redirectParams['segment_id'] = $segmentId;
        }

        return $resultRedirect->setPath('*/*/edit', $redirectParams);
    }

    /**
     * Create new segment model
     *
     * @return SegmentInterface
     */
    protected function createSegmentModel(): SegmentInterface
    {
        return $this->segmentFactory->create();
    }

}
