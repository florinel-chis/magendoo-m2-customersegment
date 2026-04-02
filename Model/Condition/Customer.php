<?php
/**
 * Magendoo CustomerSegment Customer Condition
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Condition;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Customer Attributes Condition
 */
class Customer extends AbstractCondition
{
    /**
     * @var CustomerCollectionFactory
     */
    protected CustomerCollectionFactory $customerCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var EavConfig
     */
    protected EavConfig $eavConfig;

    /**
     * @param Context $context
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param EavConfig $eavConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        CustomerCollectionFactory $customerCollectionFactory,
        StoreManagerInterface $storeManager,
        EavConfig $eavConfig,
        array $data = []
    ) {
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->storeManager = $storeManager;
        $this->eavConfig = $eavConfig;
        parent::__construct($context, $data);
    }

    /**
     * Load attribute options
     *
     * @return $this
     */
    public function loadAttributeOptions(): static
    {
        $attributes = [
            'email' => __('Email'),
            'firstname' => __('First Name'),
            'lastname' => __('Last Name'),
            'dob' => __('Date of Birth'),
            'gender' => __('Gender'),
            'taxvat' => __('Tax/VAT Number'),
            'website_id' => __('Website'),
            'store_id' => __('Store View'),
            'group_id' => __('Customer Group'),
            'created_at' => __('Account Created'),
        ];

        $this->setAttributeOption($attributes);
        return $this;
    }

    /**
     * Get attribute element
     *
     * @return \Magento\Framework\Data\Form\Element\AbstractElement
     */
    public function getAttributeElement()
    {
        $element = parent::getAttributeElement();
        $element->setShowAsText(true);
        return $element;
    }

    /**
     * Get input type for attribute
     *
     * @return string
     */
    public function getInputType(): string
    {
        $attribute = $this->getAttribute();
        
        return match ($attribute) {
            'dob', 'created_at' => 'date',
            'website_id', 'store_id', 'group_id', 'gender' => 'select',
            default => 'string',
        };
    }

    /**
     * Get value element type
     *
     * @return string
     */
    public function getValueElementType(): string
    {
        $attribute = $this->getAttribute();
        
        return match ($attribute) {
            'dob', 'created_at' => 'date',
            'website_id', 'store_id', 'group_id', 'gender' => 'select',
            default => 'text',
        };
    }

    /**
     * Get value select options
     *
     * @return array
     */
    public function getValueSelectOptions(): array
    {
        $attribute = $this->getAttribute();
        $options = [];

        try {
            switch ($attribute) {
                case 'website_id':
                    foreach ($this->storeManager->getWebsites() as $website) {
                        $options[] = [
                            'value' => $website->getId(),
                            'label' => $website->getName()
                        ];
                    }
                    break;

                case 'store_id':
                    foreach ($this->storeManager->getStores() as $store) {
                        $options[] = [
                            'value' => $store->getId(),
                            'label' => $store->getName()
                        ];
                    }
                    break;

                case 'group_id':
                    $entityType = $this->eavConfig->getEntityType('customer');
                    $attribute = $this->eavConfig->getAttribute('customer', 'group_id');
                    $options = $attribute->getSource()->getAllOptions();
                    break;

                case 'gender':
                    $attribute = $this->eavConfig->getAttribute('customer', 'gender');
                    $options = $attribute->getSource()->getAllOptions();
                    break;
            }
        } catch (LocalizedException $e) {
            // Return empty options on error
        }

        return $options;
    }

    /**
     * Get default operator options
     *
     * @return array
     */
    public function getDefaultOperatorOptions(): array
    {
        $type = $this->getInputType();
        
        return match ($type) {
            'date' => [
                '==' => __('is'),
                '!=' => __('is not'),
                '>' => __('after'),
                '<' => __('before'),
                '>=' => __('equals or after'),
                '<=' => __('equals or before'),
                'between' => __('between'),
            ],
            'select' => [
                '==' => __('is'),
                '!=' => __('is not'),
                '()' => __('is one of'),
                '!()' => __('is not one of'),
            ],
            default => [
                '==' => __('is'),
                '!=' => __('is not'),
                '{}' => __('contains'),
                '!{}' => __('does not contain'),
                '^=' => __('starts with'),
                '$=' => __('ends with'),
            ],
        };
    }

    /**
     * Validate if customer matches the condition
     *
     * @param \Magento\Customer\Model\Customer|int $customer Customer model or ID
     * @return bool
     */
    public function validate($customer): bool
    {
        if (is_numeric($customer)) {
            $customerId = (int) $customer;
        } elseif ($customer instanceof \Magento\Customer\Model\Customer) {
            $customerId = (int) $customer->getId();
        } else {
            return false;
        }

        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToFilter('entity_id', $customerId);

        // Apply the condition filter
        $attribute = $this->getAttribute();
        $operator = $this->getOperator();
        $value = $this->getValue();

        // Handle date attributes
        if ($this->getInputType() === 'date' && $value) {
            $value = date('Y-m-d', strtotime($value));
        }

        // Build the filter condition
        $condition = $this->translateOperatorToSql($operator, $value);
        $collection->addAttributeToFilter($attribute, $condition);

        return $collection->getSize() > 0;
    }

    /**
     * Translate operator to SQL condition
     *
     * @param string $operator
     * @param mixed $value
     * @return array|string
     */
    protected function translateOperatorToSql(string $operator, mixed $value): array|string
    {
        return match ($operator) {
            '==' => ['eq' => $value],
            '!=' => ['neq' => $value],
            '>' => ['gt' => $value],
            '<' => ['lt' => $value],
            '>=' => ['gteq' => $value],
            '<=' => ['lteq' => $value],
            '{}' => ['like' => '%' . $value . '%'],
            '!{}' => ['nlike' => '%' . $value . '%'],
            '^=' => ['like' => $value . '%'],
            '$=' => ['like' => '%' . $value],
            '()' => ['in' => is_array($value) ? $value : explode(',', $value)],
            '!()' => ['nin' => is_array($value) ? $value : explode(',', $value)],
            'between' => $this->buildBetweenCondition($value),
            default => ['eq' => $value],
        };
    }

    /**
     * Build between condition for date ranges
     *
     * @param mixed $value
     * @return array
     */
    protected function buildBetweenCondition(mixed $value): array
    {
        if (is_array($value)) {
            return ['from' => $value[0] ?? '', 'to' => $value[1] ?? ''];
        }

        // Parse comma-separated values
        $values = explode(',', $value);
        return [
            'from' => trim($values[0] ?? ''),
            'to' => trim($values[1] ?? '')
        ];
    }
}
