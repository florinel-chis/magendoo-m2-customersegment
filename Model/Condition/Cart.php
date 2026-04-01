<?php
/**
 * Magendoo CustomerSegment Cart Condition
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Condition;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;

/**
 * Shopping Cart Condition
 */
class Cart extends AbstractCondition
{
    /**
     * @var QuoteCollectionFactory
     */
    protected QuoteCollectionFactory $quoteCollectionFactory;

    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;

    /**
     * @var CheckoutSession|null
     */
    protected ?CheckoutSession $checkoutSession;

    /**
     * @param Context $context
     * @param QuoteCollectionFactory $quoteCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param array $data
     * @param CheckoutSession|null $checkoutSession
     */
    public function __construct(
        Context $context,
        QuoteCollectionFactory $quoteCollectionFactory,
        ResourceConnection $resourceConnection,
        array $data = [],
        ?CheckoutSession $checkoutSession = null
    ) {
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->checkoutSession = $checkoutSession;
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
            'cart_subtotal' => __('Cart Subtotal'),
            'cart_items_count' => __('Cart Items Count'),
            'cart_products' => __('Cart Contains Products (SKU)'),
            'has_active_cart' => __('Has Active Cart'),
            'cart_last_activity' => __('Days Since Cart Activity'),
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
     * Get input type
     *
     * @return string
     */
    public function getInputType(): string
    {
        $attribute = $this->getAttribute();
        
        return match ($attribute) {
            'cart_subtotal' => 'price',
            'cart_items_count' => 'numeric',
            'has_active_cart' => 'select',
            'cart_last_activity' => 'numeric',
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
            'has_active_cart' => 'select',
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
        if ($this->getAttribute() === 'has_active_cart') {
            return [
                ['value' => '1', 'label' => __('Yes')],
                ['value' => '0', 'label' => __('No')],
            ];
        }

        return [];
    }

    /**
     * Get default operator options
     *
     * @return array
     */
    public function getDefaultOperatorOptions(): array
    {
        $attribute = $this->getAttribute();
        
        return match ($attribute) {
            'has_active_cart' => [
                '==' => __('is'),
            ],
            'cart_last_activity', 'cart_items_count' => [
                '==' => __('equals'),
                '>' => __('greater than'),
                '<' => __('less than'),
            ],
            default => [
                '==' => __('is'),
                '!=' => __('is not'),
                '{}' => __('contains'),
                '!{}' => __('does not contain'),
            ],
        };
    }

    /**
     * Validate if customer matches the cart condition
     *
     * @param \Magento\Customer\Model\Customer|int $customer
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

        if (!$customerId) {
            return false;
        }

        $attribute = $this->getAttribute();
        $operator = $this->getOperator();
        $value = $this->getValue();

        $cartData = $this->getCustomerCartData($customerId);

        return $this->validateCartCondition($cartData, $attribute, $operator, $value);
    }

    /**
     * Get customer cart data
     *
     * @param int $customerId
     * @return array
     */
    protected function getCustomerCartData(int $customerId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $quoteTable = $this->resourceConnection->getTableName('quote');
        $quoteItemTable = $this->resourceConnection->getTableName('quote_item');

        // Get the active quote
        $select = $connection->select()
            ->from($quoteTable, ['entity_id', 'subtotal', 'updated_at', 'items_count'])
            ->where('customer_id = ?', $customerId)
            ->where('is_active = ?', 1)
            ->order('updated_at DESC')
            ->limit(1);

        $quote = $connection->fetchRow($select);

        if (!$quote) {
            return [
                'has_active_cart' => false,
                'cart_subtotal' => 0,
                'cart_items_count' => 0,
                'cart_last_activity' => null,
                'products' => [],
            ];
        }

        // Get products in cart
        $selectItems = $connection->select()
            ->from($quoteItemTable, ['sku'])
            ->where('quote_id = ?', $quote['entity_id'])
            ->where('parent_item_id IS NULL');

        $skus = $connection->fetchCol($selectItems);

        // Calculate days since activity
        $lastActivity = $quote['updated_at'] ?? null;
        $daysSince = null;
        if ($lastActivity) {
            $daysSince = (int) ((time() - strtotime($lastActivity)) / 86400);
        }

        return [
            'has_active_cart' => true,
            'cart_subtotal' => (float) ($quote['subtotal'] ?? 0),
            'cart_items_count' => (int) ($quote['items_count'] ?? 0),
            'cart_last_activity' => $daysSince,
            'products' => $skus,
        ];
    }

    /**
     * Validate cart condition
     *
     * @param array $cartData
     * @param string $attribute
     * @param string $operator
     * @param mixed $value
     * @return bool
     */
    protected function validateCartCondition(array $cartData, string $attribute, string $operator, mixed $value): bool
    {
        $actualValue = $cartData[$attribute] ?? null;

        // Handle has_active_cart as boolean
        if ($attribute === 'has_active_cart') {
            $hasCart = (bool) $actualValue;
            return $value === '1' ? $hasCart : !$hasCart;
        }

        // Handle cart products (SKU matching)
        if ($attribute === 'cart_products') {
            $products = $cartData['products'] ?? [];
            $searchSku = strtolower(trim($value));
            
            foreach ($products as $sku) {
                $match = match ($operator) {
                    '==' => strtolower($sku) === $searchSku,
                    '!=' => strtolower($sku) !== $searchSku,
                    '{}' => str_contains(strtolower($sku), $searchSku),
                    '!{}' => !str_contains(strtolower($sku), $searchSku),
                    default => false,
                };
                
                if ($match) {
                    return true;
                }
            }
            
            return false;
        }

        // Numeric comparisons
        if (is_numeric($actualValue) && is_numeric($value)) {
            $actualValue = (float) $actualValue;
            $value = (float) $value;

            return match ($operator) {
                '==' => $actualValue == $value,
                '!=' => $actualValue != $value,
                '>' => $actualValue > $value,
                '<' => $actualValue < $value,
                '>=' => $actualValue >= $value,
                '<=' => $actualValue <= $value,
                default => false,
            };
        }

        return false;
    }
}
