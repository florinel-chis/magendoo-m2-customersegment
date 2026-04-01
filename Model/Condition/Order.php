<?php
/**
 * Magendoo CustomerSegment Order Condition
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Condition;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

/**
 * Order History Condition
 */
class Order extends AbstractCondition
{
    /**
     * @var OrderCollectionFactory
     */
    protected OrderCollectionFactory $orderCollectionFactory;

    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;

    /**
     * @param Context $context
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param array $data
     */
    public function __construct(
        Context $context,
        OrderCollectionFactory $orderCollectionFactory,
        ResourceConnection $resourceConnection,
        array $data = []
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
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
            'total_orders' => __('Total Orders Count'),
            'total_revenue' => __('Total Revenue'),
            'average_order_value' => __('Average Order Value'),
            'first_order_date' => __('First Order Date'),
            'last_order_date' => __('Last Order Date'),
            'total_items' => __('Total Items Purchased'),
            'used_coupon' => __('Used Coupon Code'),
            'payment_method' => __('Payment Method'),
            'shipping_method' => __('Shipping Method'),
            'shipping_country' => __('Shipping Country'),
            'order_status' => __('Order Status'),
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
            'first_order_date', 'last_order_date' => 'date',
            'total_orders', 'total_items' => 'numeric',
            'total_revenue', 'average_order_value' => 'price',
            'payment_method', 'shipping_method', 'order_status' => 'select',
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
            'first_order_date', 'last_order_date' => 'date',
            'payment_method', 'shipping_method', 'order_status' => 'select',
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
            'date' => [
                '==' => __('is'),
                '!=' => __('is not'),
                '>' => __('after'),
                '<' => __('before'),
            ],
            'numeric', 'price' => [
                '==' => __('equals'),
                '!=' => __('does not equal'),
                '>' => __('greater than'),
                '<' => __('less than'),
                '>=' => __('equals or greater than'),
                '<=' => __('equals or less than'),
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
            ],
        };
    }

    /**
     * Validate if customer matches the order condition
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

        // Aggregate order data
        $orderData = $this->getCustomerOrderData($customerId);

        // No orders found
        if (empty($orderData) && $attribute !== 'total_orders') {
            return false;
        }

        return $this->validateAgainstOrderData($orderData, $attribute, $operator, $value);
    }

    /**
     * Get aggregated order data for customer
     *
     * @param int $customerId
     * @return array
     */
    protected function getCustomerOrderData(int $customerId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $select = $connection->select()
            ->from(
                $orderTable,
                [
                    'total_orders' => new Expression('COUNT(*)'),
                    'total_revenue' => new Expression('SUM(base_grand_total)'),
                    'average_order_value' => new Expression('AVG(base_grand_total)'),
                    'total_items' => new Expression('SUM(total_qty_ordered)'),
                    'first_order_date' => new Expression('MIN(created_at)'),
                    'last_order_date' => new Expression('MAX(created_at)'),
                ]
            )
            ->where('customer_id = ?', $customerId)
            ->where('state NOT IN (?)', ['canceled', 'closed']);

        return $connection->fetchRow($select) ?: [];
    }

    /**
     * Validate condition against order data
     *
     * @param array $orderData
     * @param string $attribute
     * @param string $operator
     * @param mixed $value
     * @return bool
     */
    protected function validateAgainstOrderData(array $orderData, string $attribute, string $operator, mixed $value): bool
    {
        // Special handling for non-aggregated fields
        if (in_array($attribute, ['used_coupon', 'payment_method', 'shipping_method', 'order_status'])) {
            return $this->validateOrderHistoryAttribute($orderData['customer_id'] ?? null, $attribute, $operator, $value);
        }

        $actualValue = $orderData[$attribute] ?? null;

        if ($actualValue === null) {
            return false;
        }

        // Numeric comparison
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

        // Date comparison
        if (in_array($attribute, ['first_order_date', 'last_order_date'])) {
            $actualTime = strtotime($actualValue);
            $compareTime = strtotime($value);

            return match ($operator) {
                '==' => date('Y-m-d', $actualTime) == date('Y-m-d', $compareTime),
                '!=' => date('Y-m-d', $actualTime) != date('Y-m-d', $compareTime),
                '>' => $actualTime > $compareTime,
                '<' => $actualTime < $compareTime,
                default => false,
            };
        }

        return false;
    }

    /**
     * Validate against order history (for fields not in aggregate)
     *
     * @param int|null $customerId
     * @param string $attribute
     * @param string $operator
     * @param mixed $value
     * @return bool
     */
    protected function validateOrderHistoryAttribute(?int $customerId, string $attribute, string $operator, mixed $value): bool
    {
        if (!$customerId) {
            return false;
        }

        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $column = match ($attribute) {
            'used_coupon' => 'coupon_code',
            'payment_method' => 'payment_method',
            'shipping_method' => 'shipping_method',
            'order_status' => 'status',
            default => $attribute,
        };

        $select = $connection->select()
            ->from($orderTable, 'entity_id')
            ->where('customer_id = ?', $customerId)
            ->where('state NOT IN (?)', ['canceled', 'closed']);

        // Build the condition
        $values = is_array($value) ? $value : explode(',', $value);
        $values = array_map('trim', $values);

        switch ($operator) {
            case '==':
                $select->where($column . ' = ?', $values[0]);
                break;
            case '!=':
                $select->where($column . ' != ?', $values[0]);
                break;
            case '()':
                $select->where($column . ' IN (?)', $values);
                break;
            case '!()':
                $select->where($column . ' NOT IN (?)', $values);
                break;
            default:
                $select->where($column . ' LIKE ?', '%' . $values[0] . '%');
        }

        $result = $connection->fetchOne($select);
        return (bool) $result;
    }
}
