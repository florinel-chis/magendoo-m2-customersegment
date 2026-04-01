<?php
/**
 * Magendoo CustomerSegment Conditions Tab
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Block\Adminhtml\Segment\Edit\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;
use Magento\Rule\Block\Conditions as ConditionsBlock;
use Magendoo\CustomerSegment\Model\SegmentFactory;

/**
 * Conditions tab block
 */
class Conditions extends Generic implements TabInterface
{
    /**
     * @var ConditionsBlock
     */
    protected $conditionsBlock;

    /**
     * @var Fieldset
     */
    protected $rendererFieldset;

    /**
     * @var SegmentFactory
     */
    protected $segmentFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param ConditionsBlock $conditionsBlock
     * @param Fieldset $rendererFieldset
     * @param SegmentFactory $segmentFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        ConditionsBlock $conditionsBlock,
        Fieldset $rendererFieldset,
        SegmentFactory $segmentFactory,
        array $data = []
    ) {
        $this->conditionsBlock = $conditionsBlock;
        $this->rendererFieldset = $rendererFieldset;
        $this->segmentFactory = $segmentFactory;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @inheritdoc
     */
    public function getTabLabel(): string
    {
        return __('Conditions');
    }

    /**
     * @inheritdoc
     */
    public function getTabTitle(): string
    {
        return __('Conditions');
    }

    /**
     * @inheritdoc
     */
    public function canShowTab(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function isHidden(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function isAjaxLoaded(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getTabUrl(): string|null
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getTabClass(): string|null
    {
        return null;
    }

    /**
     * Prepare form
     *
     * @return $this
     */
    protected function _prepareForm()
    {
        $model = $this->_coreRegistry->registry('current_segment');
        
        if (!$model) {
            $id = $this->getRequest()->getParam('segment_id');
            $model = $this->segmentFactory->create();
            if ($id) {
                $model->load($id);
            }
        }

        $formName = 'customersegment_segment_form';
        $conditionsFieldSetId = $model->getId() ? 'segment_conditions_fieldset_' . $model->getId() : 'segment_conditions_fieldset';
        
        $newChildUrl = $this->getUrl(
            'customersegment/segment/newConditionHtml/form/' . $conditionsFieldSetId,
            ['form_namespace' => $formName]
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
            ['legend' => __('Define customer segment conditions')]
        )->setRenderer($renderer);

        $fieldset->addField(
            'conditions',
            'text',
            [
                'name'           => 'conditions',
                'label'          => __('Conditions'),
                'title'          => __('Conditions'),
                'required'       => false,
                'data-form-part' => $formName,
            ]
        )->setRule($model)
          ->setRenderer($this->conditionsBlock);

        $form->setValues($model->getData());
        
        if ($model->getConditions()) {
            $this->setConditionFormName($model->getConditions(), $formName);
        }
        
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Set condition form name recursively
     *
     * @param \Magento\Rule\Model\Condition\AbstractCondition $conditions
     * @param string $formName
     * @return void
     */
    private function setConditionFormName(\Magento\Rule\Model\Condition\AbstractCondition $conditions, string $formName): void
    {
        $conditions->setFormName($formName);
        $conditions->setJsFormObject($formName);
        
        if ($conditions->getConditions() && is_array($conditions->getConditions())) {
            foreach ($conditions->getConditions() as $condition) {
                $this->setConditionFormName($condition, $formName);
            }
        }
    }
}
