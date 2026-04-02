<?php
/**
 * Magendoo CustomerSegment Product Condition
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Condition;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;

/**
 * Product Interactions Condition
 *
 * Conditions based on customer product interactions:
 * - Viewed categories
 * - Purchased products
 * - Purchased categories
 * - Wishlist items count
 */
class Product extends AbstractCondition
{
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
            'viewed_categories' => __('Viewed Categories'),
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
        $attribute = $this->getAttribute();

        return match ($attribute) {
            'wishlist_items_count' => 'text',
            default => 'text',
        };
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
            'numeric' => [
                '==' => __('equals'),
                '!=' => __('does not equal'),
                '>' => __('greater than'),
                '<' => __('less than'),
                '>=' => __('equals or greater than'),
                '<=' => __('equals or less than'),
                'between' => __('between'),
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
     * Validate if customer matches the product condition
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

        return match ($attribute) {
            'purchased_products' => $this->validatePurchasedProducts($customerId, $operator, $value),
            'purchased_categories' => $this->validatePurchasedCategories($customerId, $operator, $value),
            'wishlist_items_count' => $this->validateWishlistItemsCount($customerId, $operator, $value),
            'viewed_categories' => $this->validateViewedCategories($customerId, $operator, $value),
            default => false,
        };
    }

    /**
     * Validate purchased products
     *
     * @param int $customerId
     * @param string $operator
     * @param string $value
     * @return bool
     */
    protected function validatePurchasedProducts(int $customerId, string $operator, string $value): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $orderItemTable = $this->resourceConnection->getTableName('sales_order_item');

        $select = $connection->select()
            ->from(['o' => $orderTable], ['item_count' => 'COUNT(*)'])
            ->join(['oi' => $orderItemTable], 'o.entity_id = oi.order_id', [])
            ->where('o.customer_id = ?', $customerId)
            ->where('o.state NOT IN (?)', ['canceled', 'closed'])
            ->where('oi.product_type = ?', 'simple');

        // Check for specific SKU match
        $skus = array_map('trim', explode(',', $value));
        if ($operator === '==') {
            $select->where('oi.sku IN (?)', $skus);
        } elseif ($operator === '!=') {
            $select->where('oi.sku NOT IN (?)', $skus);
        } elseif ($operator === '{}') {
            $conditions = [];
            foreach ($skus as $sku) {
                $conditions[] = $connection->quoteInto('oi.sku LIKE ?', '%' . $sku . '%');
            }
            $select->where('(' . implode(' OR ', $conditions) . ')');
        } elseif ($operator === '!{}') {
            foreach ($skus as $sku) {
                $select->where('oi.sku NOT LIKE ?', '%' . $sku . '%');
            }
        }

        $result = $connection->fetchOne($select);
        return (int) $result > 0;
    }

    /**
     * Validate purchased categories
     *
     * @param int $customerId
     * @param string $operator
     * @param string $value
     * @return bool
     */
    protected function validatePurchasedCategories(int $customerId, string $operator, string $value): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $orderItemTable = $this->resourceConnection->getTableName('sales_order_item');
        $categoryProductTable = $this->resourceConnection->getTableName('catalog_category_product');

        $categoryIds = array_map('intval', explode(',', $value));

        $select = $connection->select()
            ->from(['o' => $orderTable], ['item_count' => 'COUNT(DISTINCT oi.product_id)'])
            ->join(['oi' => $orderItemTable], 'o.entity_id = oi.order_id', [])
            ->join(['cp' => $categoryProductTable], 'oi.product_id = cp.product_id', [])
            ->where('o.customer_id = ?', $customerId)
            ->where('o.state NOT IN (?)', ['canceled', 'closed']);

        if ($operator === '==') {
            $select->where('cp.category_id IN (?)', $categoryIds);
        } elseif ($operator === '!=') {
            $select->where('cp.category_id NOT IN (?)', $categoryIds);
        }

        $result = $connection->fetchOne($select);
        return (int) $result > 0;
    }

    /**
     * Validate wishlist items count
     *
     * @param int $customerId
     * @param string $operator
     * @param mixed $value
     * @return bool
     */
    protected function validateWishlistItemsCount(int $customerId, string $operator, mixed $value): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $wishlistTable = $this->resourceConnection->getTableName('wishlist');
        $wishlistItemTable = $this->resourceConnection->getTableName('wishlist_item');

        $select = $connection->select()
            ->from(['w' => $wishlistTable], [])
            ->join(['wi' => $wishlistItemTable], 'w.wishlist_id = wi.wishlist_id', ['item_count' => 'COUNT(*)'])
            ->where('w.customer_id = ?', $customerId);

        $actualCount = (int) $connection->fetchOne($select);

        return match ($operator) {
            '==' => $actualCount == $value,
            '!=' => $actualCount != $value,
            '>' => $actualCount > $value,
            '<' => $actualCount < $value,
            '>=' => $actualCount >= $value,
            '<=' => $actualCount <= $value,
            'between' => $this->isValueBetween($actualCount, $value),
            default => false,
        };
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
            $values = explode(',', $rangeValue);
            $min = (float) trim($values[0] ?? 0);
            $max = (float) trim($values[1] ?? 0);
        }

        return $actualValue >= $min && $actualValue <= $max;
    }

    /**
     * Validate viewed categories (placeholder - requires reports data)
     *
     * @param int $customerId
     * @param string $operator
     * @param string $value
     * @return bool
     */
    protected function validateViewedCategories(int $customerId, string $operator, string $value): bool
    {
        // This would require integration with reports/product view tracking
        // For now, return false as this requires additional data sources
        return false;
    }
}
