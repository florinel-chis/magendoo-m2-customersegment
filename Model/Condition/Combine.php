<?php
/**
 * Magendoo CustomerSegment - condition combine (aggregator)
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
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
class Combine extends BaseCombine implements SetBasedInterface
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
     * @var Product
     */
    protected Product $conditionProduct;

    /**
     * @param Context $context
     * @param ManagerInterface $eventManager
     * @param Customer $conditionCustomer
     * @param Order $conditionOrder
     * @param Cart $conditionCart
     * @param Product $conditionProduct
     * @param array $data
     */
    public function __construct(
        Context $context,
        ManagerInterface $eventManager,
        Customer $conditionCustomer,
        Order $conditionOrder,
        Cart $conditionCart,
        Product $conditionProduct,
        array $data = []
    ) {
        $this->eventManager = $eventManager;
        $this->conditionCustomer = $conditionCustomer;
        $this->conditionOrder = $conditionOrder;
        $this->conditionCart = $conditionCart;
        $this->conditionProduct = $conditionProduct;
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

        // Product attributes
        $productAttributes = $this->conditionProduct->loadAttributeOptions()->getAttributeOption();
        $productOptions = [];
        foreach ($productAttributes as $code => $label) {
            $productOptions[] = [
                'value' => Product::class . '|' . $code,
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
            ],
            [
                'label' => __('Product Interactions'),
                'value' => $productOptions
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

    /**
     * Resolve the full matching customer set for this combine
     *
     * 'all' intersects the child sets; 'any' unions them. If ANY child (leaf or nested combine)
     * cannot be resolved set-based (returns null) the whole combine returns null and the caller
     * falls back to per-customer validate(). An empty combine returns null (membership of an empty
     * combine is a product decision handled by the segment manager, not silently "everyone").
     *
     * @return int[]|null
     */
    public function getMatchingCustomerIds(): ?array
    {
        $conditions = $this->getConditions();
        if (!$conditions) {
            return null;
        }

        $isAll = $this->getAggregator() === 'all';
        $childSets = [];

        foreach ($conditions as $condition) {
            if (!$condition instanceof SetBasedInterface) {
                return null;
            }

            $childIds = $condition->getMatchingCustomerIds();
            if ($childIds === null) {
                return null;
            }

            $childSets[] = array_map('intval', $childIds);
        }

        if (!$childSets) {
            return null;
        }

        if ($isAll) {
            $result = array_shift($childSets);
            foreach ($childSets as $set) {
                $result = array_intersect($result, $set);
            }
        } else {
            $result = array_merge(...$childSets);
        }

        return array_values(array_unique($result));
    }
}
