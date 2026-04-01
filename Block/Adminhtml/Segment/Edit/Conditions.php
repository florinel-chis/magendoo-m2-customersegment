<?php
/**
 * Magendoo CustomerSegment Conditions Block
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Block\Adminhtml\Segment\Edit;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;
use Magento\Rule\Block\Conditions as ConditionsRenderer;
use Magendoo\CustomerSegment\Model\SegmentFactory;

/**
 * Conditions block for segment edit form
 */
class Conditions extends Generic
{
    /**
     * @var ConditionsRenderer
     */
    protected ConditionsRenderer $conditionsRenderer;

    /**
     * @var Fieldset
     */
    protected Fieldset $fieldsetRenderer;

    /**
     * @var SegmentFactory
     */
    protected SegmentFactory $segmentFactory;

    /**
     * @var DataPersistorInterface
     */
    protected DataPersistorInterface $dataPersistor;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param ConditionsRenderer $conditionsRenderer
     * @param Fieldset $fieldsetRenderer
     * @param SegmentFactory $segmentFactory
     * @param DataPersistorInterface $dataPersistor
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        ConditionsRenderer $conditionsRenderer,
        Fieldset $fieldsetRenderer,
        SegmentFactory $segmentFactory,
        DataPersistorInterface $dataPersistor,
        array $data = []
    ) {
        $this->conditionsRenderer = $conditionsRenderer;
        $this->fieldsetRenderer = $fieldsetRenderer;
        $this->segmentFactory = $segmentFactory;
        $this->dataPersistor = $dataPersistor;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * Prepare form
     *
     * @return $this
     */
    protected function _prepareForm()
    {
        $segment = $this->dataPersistor->get('current_segment');
        
        if (!$segment) {
            $id = $this->getRequest()->getParam('segment_id');
            $segment = $this->segmentFactory->create();
            if ($id) {
                $segment->load($id);
            }
        }

        $formName = 'customersegment_segment_form';
        $conditionsFieldSetId = $segment->getConditionsFieldSetId($formName);
        
        $newChildUrl = $this->getUrl(
            'customersegment/segment/newConditionHtml',
            ['form' => $conditionsFieldSetId, 'form_namespace' => $formName]
        );

        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('segment_');

        $renderer = $this->getLayout()->createBlock(Fieldset::class);
        $renderer->setTemplate('Magento_CatalogRule::promo/fieldset.phtml')
            ->setNewChildUrl($newChildUrl)
            ->setFieldSetId($conditionsFieldSetId);

        $fieldset = $form->addFieldset(
            'conditions_fieldset',
            [
                'legend' => __('Apply the rule only if the following conditions are met'),
            ]
        )->setRenderer($renderer);

        $fieldset->addField(
            'conditions',
            'text',
            [
                'name' => 'conditions',
                'label' => __('Conditions'),
                'title' => __('Conditions'),
                'data-form-part' => $formName,
            ]
        )->setRule($segment)
          ->setRenderer($this->conditionsRenderer);

        $form->setValues($segment->getData());
        $this->setForm($form);

        // Set form name on all conditions recursively
        if ($segment->getConditions()) {
            $this->setConditionFormName($segment->getConditions(), $formName);
        }

        return parent::_prepareForm();
    }

    /**
     * Get conditions HTML for rendering in template
     *
     * @return string
     */
    public function getConditionsHtml(): string
    {
        return $this->getForm()->toHtml();
    }

    /**
     * Get current segment
     *
     * @return \Magendoo\CustomerSegment\Model\Segment
     */
    public function getSegment(): \Magendoo\CustomerSegment\Model\Segment
    {
        $segment = $this->dataPersistor->get('current_segment');
        if (!$segment) {
            $segment = $this->segmentFactory->create();
        }
        return $segment;
    }

    /**
     * Get form name
     *
     * @return string
     */
    public function getFormName(): string
    {
        return 'customersegment_segment_form';
    }

    /**
     * Get conditions field set id
     *
     * @return string
     */
    public function getConditionsFieldSetId(): string
    {
        return $this->getSegment()->getConditionsFieldSetId($this->getFormName());
    }

    /**
     * Get new child URL for adding conditions
     *
     * @return string
     */
    public function getNewChildUrl(): string
    {
        return $this->getUrl(
            'customersegment/segment/newConditionHtml',
            [
                'form' => $this->getConditionsFieldSetId(),
                'form_namespace' => $this->getFormName()
            ]
        );
    }

    /**
     * Set form name on condition and its children
     *
     * @param \Magento\Rule\Model\Condition\AbstractCondition $conditions
     * @param string $formName
     * @return void
     */
    private function setConditionFormName(\Magento\Rule\Model\Condition\AbstractCondition $conditions, string $formName): void
    {
        $conditions->setFormName($formName);
        if ($conditions->getConditions() && is_array($conditions->getConditions())) {
            foreach ($conditions->getConditions() as $condition) {
                $this->setConditionFormName($condition, $formName);
            }
        }
    }
}
