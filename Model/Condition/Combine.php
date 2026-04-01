<?php
/**
 * Magendoo CustomerSegment Condition Combine
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Condition;

use Magento\Framework\Event\ManagerInterface;
use Magento\Rule\Model\Condition\Combine as BaseCombine;
use Magento\Rule\Model\Condition\Context;

/**
 * Customer Segment Condition Combine
 *
 * This class combines all available conditions for customer segments
 */
class Combine extends BaseCombine
{
    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $eventManager;

    /**
     * @var Customer
     */
    protected Customer $conditionCustomer;

    /**
     * @var Order
     */
    protected Order $conditionOrder;

    /**
     * @var Cart
     */
    protected Cart $conditionCart;

    /**
     * @param Context $context
     * @param ManagerInterface $eventManager
     * @param Customer $conditionCustomer
     * @param Order $conditionOrder
     * @param Cart $conditionCart
     * @param array $data
     */
    public function __construct(
        Context $context,
        ManagerInterface $eventManager,
        Customer $conditionCustomer,
        Order $conditionOrder,
        Cart $conditionCart,
        array $data = []
    ) {
        $this->eventManager = $eventManager;
        $this->conditionCustomer = $conditionCustomer;
        $this->conditionOrder = $conditionOrder;
        $this->conditionCart = $conditionCart;
        parent::__construct($context, $data);
        $this->setType(self::class);
    }

    /**
     * Get new child select options
     *
     * @return array
     */
    public function getNewChildSelectOptions(): array
    {
        // Customer attributes
        $customerAttributes = $this->conditionCustomer->loadAttributeOptions()->getAttributeOption();
        $customerOptions = [];
        foreach ($customerAttributes as $code => $label) {
            $customerOptions[] = [
                'value' => Customer::class . '|' . $code,
                'label' => $label,
            ];
        }

        // Order attributes
        $orderAttributes = $this->conditionOrder->loadAttributeOptions()->getAttributeOption();
        $orderOptions = [];
        foreach ($orderAttributes as $code => $label) {
            $orderOptions[] = [
                'value' => Order::class . '|' . $code,
                'label' => $label,
            ];
        }

        // Cart attributes
        $cartAttributes = $this->conditionCart->loadAttributeOptions()->getAttributeOption();
        $cartOptions = [];
        foreach ($cartAttributes as $code => $label) {
            $cartOptions[] = [
                'value' => Cart::class . '|' . $code,
                'label' => $label,
            ];
        }

        $conditions = [
            [
                'value' => self::class,
                'label' => __('Conditions Combination')
            ],
            [
                'label' => __('Customer Attributes'),
                'value' => $customerOptions
            ],
            [
                'label' => __('Order History'),
                'value' => $orderOptions
            ],
            [
                'label' => __('Shopping Cart'),
                'value' => $cartOptions
            ]
        ];

        // Allow other modules to add custom conditions
        $additional = new \Magento\Framework\DataObject();
        $this->eventManager->dispatch('magendoo_customersegment_conditions', ['additional' => $additional]);
        $additionalConditions = $additional->getConditions();
        if ($additionalConditions) {
            $conditions = array_merge_recursive($conditions, $additionalConditions);
        }

        return $conditions;
    }

    /**
     * Validate if customer matches the combined conditions
     *
     * @param mixed $customer
     * @return bool
     */
    public function validate($customer): bool
    {
        if (!$this->getConditions()) {
            return true;
        }

        $allValid = true;
        foreach ($this->getConditions() as $condition) {
            $validated = $condition->validate($customer);
            
            if ($this->getAggregator() === 'all' && !$validated) {
                return false;
            }
            if ($this->getAggregator() === 'any' && $validated) {
                return true;
            }
            
            $allValid = $allValid && $validated;
        }

        return $this->getAggregator() === 'all' ? $allValid : false;
    }
}
