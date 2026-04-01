<?php
/**
 * Magendoo CustomerSegment New Condition HTML Controller
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
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\ConditionInterface;
use Magendoo\CustomerSegment\Model\SegmentFactory;

/**
 * New Condition HTML Controller
 */
class NewConditionHtml extends Action implements HttpPostActionInterface
{
    /**
     * Authorization resource
     */
    public const ADMIN_RESOURCE = 'Magendoo_CustomerSegment::segment_edit';

    /**
     * @var RawFactory
     */
    protected RawFactory $resultRawFactory;

    /**
     * @var SegmentFactory
     */
    protected SegmentFactory $segmentFactory;

    /**
     * @param Context $context
     * @param RawFactory $resultRawFactory
     * @param SegmentFactory $segmentFactory
     */
    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        SegmentFactory $segmentFactory
    ) {
        $this->resultRawFactory = $resultRawFactory;
        $this->segmentFactory = $segmentFactory;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $formName = $this->getRequest()->getParam('form_namespace');
        $id = $this->getRequest()->getParam('id');
        $typeArray = explode(
            '|',
            str_replace('-', '/', $this->getRequest()->getParam('type', ''))
        );
        $type = $typeArray[0];

        // Validate the condition type implements ConditionInterface
        if ($type && class_exists($type) && !in_array(ConditionInterface::class, class_implements($type))) {
            $html = '';
            $this->getResponse()->setBody($html);
            return $this->resultRawFactory->create()->setContents($html);
        }

        // Create a new segment as the "rule" for the condition
        $segment = $this->segmentFactory->create();

        /** @var AbstractCondition $model */
        $model = $this->_objectManager->create($type)
            ->setId($id)
            ->setType($type)
            ->setRule($segment)
            ->setPrefix('conditions');

        if (!empty($typeArray[1])) {
            $model->setAttribute($typeArray[1]);
        }

        if ($model instanceof AbstractCondition) {
            $model->setJsFormObject($this->getRequest()->getParam('form'));
            $model->setFormName($formName);
            $this->setJsFormObject($model);
            $html = $model->asHtmlRecursive();
        } else {
            $html = '';
        }

        return $this->resultRawFactory->create()->setContents($html);
    }

    /**
     * Set jsFormObject for the model object
     *
     * @param AbstractCondition $model
     * @return void
     */
    private function setJsFormObject(AbstractCondition $model): void
    {
        $requestJsFormName = $this->getRequest()->getParam('form');
        $actualJsFormName = $this->getJsFormObjectName($model->getFormName());
        if ($requestJsFormName === $actualJsFormName) {
            $model->setJsFormObject($actualJsFormName);
        }
    }

    /**
     * Get jsFormObject name
     *
     * @param string $formName
     * @return string
     */
    private function getJsFormObjectName(string $formName): string
    {
        return $formName . '_conditions_fieldset';
    }
}
