<?php
/**
 * Magendoo CustomerSegment - shopping cart condition
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Condition;

use Magento\Framework\App\ResourceConnection;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;

/**
 * Shopping Cart Condition
 */
class Cart extends AbstractCondition implements SetBasedInterface
{
    /**
     * Operators that negate at the customer level.
     */
    private const NEGATIVE_OPERATORS = ['!=', '!{}'];

    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;

    /**
     * @param Context $context
     * @param ResourceConnection $resourceConnection
     * @param array $data
     */
    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        array $data = []
    ) {
        $this->resourceConnection = $resourceConnection;
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
            'cart_items_count', 'cart_last_activity' => 'numeric',
            'has_active_cart' => 'select',
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

        if ($attribute === 'has_active_cart') {
            return ['==' => __('is')];
        }

        if ($attribute === 'cart_products') {
            return [
                '==' => __('is'),
                '!=' => __('is not'),
                '{}' => __('contains'),
                '!{}' => __('does not contain'),
            ];
        }

        // Numeric attributes: cart_subtotal, cart_items_count, cart_last_activity.
        return [
            '==' => __('equals'),
            '!=' => __('does not equal'),
            '>' => __('greater than'),
            '<' => __('less than'),
            '>=' => __('equals or greater than'),
            '<=' => __('equals or less than'),
        ];
    }

    /**
     * Validate if customer matches the cart condition
     *
     * @param \Magento\Customer\Model\Customer|int $customer
     * @return bool
     */
    public function validate($customer): bool
    {
        $customerId = $this->resolveCustomerId($customer);
        if ($customerId === null) {
            return false;
        }

        $attribute = (string) $this->getAttribute();
        $operator = (string) $this->getOperator();
        $value = $this->getValue();

        $cartData = $this->getCustomerCartData($customerId);

        return $this->validateCartCondition($cartData, $attribute, $operator, $value);
    }

    /**
     * Resolve matching customer ids for the set-resolvable "has active cart = yes" case
     *
     * Subtotal/items/last-activity depend on each customer's most-recent active quote and the
     * negative/empty cases require the full customer universe, so those return null (fall back).
     *
     * @return int[]|null
     */
    public function getMatchingCustomerIds(): ?array
    {
        if ($this->getAttribute() !== 'has_active_cart'
            || (string) $this->getOperator() !== '=='
            || (string) $this->getValue() !== '1'
        ) {
            return null;
        }

        $connection = $this->resourceConnection->getConnection();
        $quoteTable = $this->resourceConnection->getTableName('quote');

        $select = $connection->select()
            ->distinct(true)
            ->from($quoteTable, ['customer_id'])
            ->where('is_active = ?', 1)
            ->where('customer_id IS NOT NULL');

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Get customer cart data (most recent active quote)
     *
     * @param int $customerId
     * @return array
     */
    protected function getCustomerCartData(int $customerId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $quoteTable = $this->resourceConnection->getTableName('quote');
        $quoteItemTable = $this->resourceConnection->getTableName('quote_item');

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

        $selectItems = $connection->select()
            ->from($quoteItemTable, ['sku'])
            ->where('quote_id = ?', $quote['entity_id'])
            ->where('parent_item_id IS NULL');

        $skus = $connection->fetchCol($selectItems);

        $lastActivity = $quote['updated_at'] ?? null;
        $daysSince = null;
        if ($lastActivity) {
            $activityTs = $this->toUtcTimestamp((string) $lastActivity);
            if ($activityTs !== null) {
                $daysSince = (int) floor((time() - $activityTs) / 86400);
            }
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
        if ($attribute === 'has_active_cart') {
            $hasCart = (bool) ($cartData['has_active_cart'] ?? false);
            return (string) $value === '1' ? $hasCart : !$hasCart;
        }

        if ($attribute === 'cart_products') {
            return $this->validateCartProducts($cartData['products'] ?? [], $operator, (string) $value);
        }

        $actualValue = $cartData[$attribute] ?? null;

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

    /**
     * Validate SKU membership; negation matches only when NO cart line satisfies the positive predicate
     *
     * @param array $products
     * @param string $operator
     * @param string $value
     * @return bool
     */
    private function validateCartProducts(array $products, string $operator, string $value): bool
    {
        $searchSku = strtolower(trim($value));
        $isNegative = in_array($operator, self::NEGATIVE_OPERATORS, true);

        $positiveOperator = match ($operator) {
            '!=' => '==',
            '!{}' => '{}',
            default => $operator,
        };

        $anyMatch = false;
        foreach ($products as $sku) {
            $sku = strtolower((string) $sku);
            $match = match ($positiveOperator) {
                '==' => $sku === $searchSku,
                '{}' => $searchSku !== '' && str_contains($sku, $searchSku),
                default => false,
            };

            if ($match) {
                $anyMatch = true;
                break;
            }
        }

        // Empty cart falls through as $anyMatch === false: "does not contain X" is true, "contains X" is false.
        return $isNegative ? !$anyMatch : $anyMatch;
    }

    /**
     * Parse a stored (UTC) timestamp string into a UTC epoch
     *
     * @param string $value
     * @return int|null
     */
    private function toUtcTimestamp(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->getTimestamp();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Resolve a customer id from a model or scalar id
     *
     * @param mixed $customer
     * @return int|null
     */
    private function resolveCustomerId(mixed $customer): ?int
    {
        if (is_numeric($customer)) {
            $customerId = (int) $customer;
        } elseif ($customer instanceof \Magento\Customer\Model\Customer) {
            $customerId = (int) $customer->getId();
        } else {
            return null;
        }

        return $customerId > 0 ? $customerId : null;
    }
}
