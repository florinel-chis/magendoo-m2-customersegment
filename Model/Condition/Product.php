<?php
/**
 * Magendoo CustomerSegment - product interactions condition
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Condition;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;

/**
 * Product Interactions Condition
 *
 * Conditions based on customer product interactions:
 * - Purchased products (SKU)
 * - Purchased from categories
 * - Wishlist items count
 */
class Product extends AbstractCondition implements SetBasedInterface
{
    /**
     * Order states excluded from purchase queries.
     */
    private const EXCLUDED_STATES = ['canceled', 'closed'];

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
            'purchased_products' => __('Purchased Products (SKU)'),
            'purchased_categories' => __('Purchased from Categories'),
            'wishlist_items_count' => __('Wishlist Items Count'),
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
            'wishlist_items_count' => 'numeric',
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
        return 'text';
    }

    /**
     * Get default operator options
     *
     * @return array
     */
    public function getDefaultOperatorOptions(): array
    {
        $attribute = $this->getAttribute();

        if ($attribute === 'wishlist_items_count') {
            return [
                '==' => __('equals'),
                '!=' => __('does not equal'),
                '>' => __('greater than'),
                '<' => __('less than'),
                '>=' => __('equals or greater than'),
                '<=' => __('equals or less than'),
                'between' => __('between'),
            ];
        }

        if ($attribute === 'purchased_categories') {
            return [
                '==' => __('is one of'),
                '!=' => __('is not one of'),
            ];
        }

        // purchased_products (SKU).
        return [
            '==' => __('is'),
            '!=' => __('is not'),
            '{}' => __('contains'),
            '!{}' => __('does not contain'),
        ];
    }

    /**
     * Validate if customer matches the product condition
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
        $value = (string) $this->getValue();

        return match ($attribute) {
            'purchased_products' => $this->validatePurchasedProducts($customerId, $operator, $value),
            'purchased_categories' => $this->validatePurchasedCategories($customerId, $operator, $value),
            'wishlist_items_count' => $this->validateWishlistItemsCount($customerId, $operator, $value),
            default => false,
        };
    }

    /**
     * Resolve matching customer ids for the set-resolvable positive purchase attributes
     *
     * Negations require the full customer universe and wishlist count is left to the fallback path,
     * so those return null.
     *
     * @return int[]|null
     */
    public function getMatchingCustomerIds(): ?array
    {
        $attribute = (string) $this->getAttribute();
        $operator = (string) $this->getOperator();
        $value = (string) $this->getValue();
        $connection = $this->resourceConnection->getConnection();

        if ($attribute === 'purchased_products' && in_array($operator, ['==', '{}'], true)) {
            $select = $this->buildPurchaseSelect()
                ->distinct(true)
                ->columns(['customer_id' => 'o.customer_id'])
                ->where('o.customer_id IS NOT NULL');
            $this->applySkuPredicate($select, $operator, $value);
            return array_map('intval', $connection->fetchCol($select));
        }

        if ($attribute === 'purchased_categories' && $operator === '==') {
            $select = $this->buildCategoryPurchaseSelect($value)
                ->distinct(true)
                ->columns(['customer_id' => 'o.customer_id'])
                ->where('o.customer_id IS NOT NULL');
            return array_map('intval', $connection->fetchCol($select));
        }

        return null;
    }

    /**
     * Validate purchased products (existence-then-negate for negative operators)
     *
     * @param int $customerId
     * @param string $operator
     * @param string $value
     * @return bool
     */
    protected function validatePurchasedProducts(int $customerId, string $operator, string $value): bool
    {
        $isNegative = in_array($operator, ['!=', '!{}'], true);
        $positiveOperator = match ($operator) {
            '!=' => '==',
            '!{}' => '{}',
            default => $operator,
        };

        $connection = $this->resourceConnection->getConnection();
        $select = $this->buildPurchaseSelect()
            ->columns(['item_count' => 'COUNT(*)'])
            ->where('o.customer_id = ?', $customerId);
        $this->applySkuPredicate($select, $positiveOperator, $value);

        $exists = (int) $connection->fetchOne($select) > 0;

        return $isNegative ? !$exists : $exists;
    }

    /**
     * Validate purchased categories (existence-then-negate for the negative operator)
     *
     * @param int $customerId
     * @param string $operator
     * @param string $value
     * @return bool
     */
    protected function validatePurchasedCategories(int $customerId, string $operator, string $value): bool
    {
        $isNegative = ($operator === '!=');

        $connection = $this->resourceConnection->getConnection();
        $select = $this->buildCategoryPurchaseSelect($value)
            ->columns(['item_count' => 'COUNT(DISTINCT oi.product_id)'])
            ->where('o.customer_id = ?', $customerId);

        $exists = (int) $connection->fetchOne($select) > 0;

        return $isNegative ? !$exists : $exists;
    }

    /**
     * Validate wishlist items count
     *
     * @param int $customerId
     * @param string $operator
     * @param string $value
     * @return bool
     */
    protected function validateWishlistItemsCount(int $customerId, string $operator, string $value): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $wishlistTable = $this->resourceConnection->getTableName('wishlist');
        $wishlistItemTable = $this->resourceConnection->getTableName('wishlist_item');

        $select = $connection->select()
            ->from(['w' => $wishlistTable], [])
            ->join(['wi' => $wishlistItemTable], 'w.wishlist_id = wi.wishlist_id', ['item_count' => 'COUNT(*)'])
            ->where('w.customer_id = ?', $customerId);

        $actualCount = (int) $connection->fetchOne($select);
        $compare = (float) $value;

        return match ($operator) {
            '==' => $actualCount == $compare,
            '!=' => $actualCount != $compare,
            '>' => $actualCount > $compare,
            '<' => $actualCount < $compare,
            '>=' => $actualCount >= $compare,
            '<=' => $actualCount <= $compare,
            'between' => $this->isValueBetween((float) $actualCount, $value),
            default => false,
        };
    }

    /**
     * Base select over a customer's non-cancelled orders joined to top-level order items
     *
     * Filtering parent_item_id IS NULL keeps one row per ordered line and covers simple, virtual,
     * downloadable and bundle purchases (child rows of composite products are excluded).
     *
     * @return Select
     */
    private function buildPurchaseSelect(): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $orderItemTable = $this->resourceConnection->getTableName('sales_order_item');

        return $connection->select()
            ->from(['o' => $orderTable], [])
            ->join(['oi' => $orderItemTable], 'o.entity_id = oi.order_id', [])
            ->where('o.state NOT IN (?)', self::EXCLUDED_STATES)
            ->where('oi.parent_item_id IS NULL');
    }

    /**
     * Purchase select additionally constrained to the given category ids
     *
     * @param string $value
     * @return Select
     */
    private function buildCategoryPurchaseSelect(string $value): Select
    {
        $categoryProductTable = $this->resourceConnection->getTableName('catalog_category_product');
        $categoryIds = array_values(array_filter(array_map('intval', explode(',', $value))));
        if (!$categoryIds) {
            $categoryIds = [0];
        }

        return $this->buildPurchaseSelect()
            ->join(['cp' => $categoryProductTable], 'oi.product_id = cp.product_id', [])
            ->where('cp.category_id IN (?)', $categoryIds);
    }

    /**
     * Apply the (positive) SKU predicate to a purchase select
     *
     * @param Select $select
     * @param string $operator
     * @param string $value
     * @return void
     */
    private function applySkuPredicate(Select $select, string $operator, string $value): void
    {
        $connection = $this->resourceConnection->getConnection();
        $skus = array_map('trim', explode(',', $value));

        if ($operator === '{}') {
            $conditions = [];
            foreach ($skus as $sku) {
                $conditions[] = $connection->quoteInto('oi.sku LIKE ?', '%' . $sku . '%');
            }
            $select->where('(' . implode(' OR ', $conditions) . ')');
            return;
        }

        $select->where('oi.sku IN (?)', $skus);
    }

    /**
     * Check if value is between range
     *
     * @param float $actualValue
     * @param mixed $rangeValue
     * @return bool
     */
    protected function isValueBetween(float $actualValue, mixed $rangeValue): bool
    {
        if (is_array($rangeValue)) {
            $min = (float) ($rangeValue[0] ?? 0);
            $max = (float) ($rangeValue[1] ?? 0);
        } else {
            $values = explode(',', (string) $rangeValue);
            $min = (float) trim($values[0] ?? '0');
            $max = (float) trim($values[1] ?? '0');
        }

        return $actualValue >= $min && $actualValue <= $max;
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
