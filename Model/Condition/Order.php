<?php
/**
 * Magendoo CustomerSegment - order history condition
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Condition;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

/**
 * Order History Condition
 */
class Order extends AbstractCondition implements SetBasedInterface
{
    /**
     * Order states excluded from every aggregate/existence query.
     */
    private const EXCLUDED_STATES = ['canceled', 'closed'];

    /**
     * Attributes evaluated as an existence check over individual orders (not the aggregate row).
     */
    private const EXISTENCE_ATTRIBUTES = [
        'used_coupon',
        'payment_method',
        'shipping_method',
        'order_status',
        'shipping_country',
    ];

    /**
     * Date attributes derived from the aggregate row.
     */
    private const DATE_ATTRIBUTES = ['first_order_date', 'last_order_date'];

    /**
     * Operators that negate at the customer level.
     */
    private const NEGATIVE_OPERATORS = ['!=', '!()', '!{}'];

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
                'between' => __('between'),
            ],
            'numeric', 'price' => [
                '==' => __('equals'),
                '!=' => __('does not equal'),
                '>' => __('greater than'),
                '<' => __('less than'),
                '>=' => __('equals or greater than'),
                '<=' => __('equals or less than'),
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
        $customerId = $this->resolveCustomerId($customer);
        if ($customerId === null) {
            return false;
        }

        $attribute = (string) $this->getAttribute();
        $operator = (string) $this->getOperator();
        $value = $this->getValue();

        if (in_array($attribute, self::EXISTENCE_ATTRIBUTES, true)) {
            return $this->validateExistence($customerId, $attribute, $operator, $value);
        }

        $orderData = $this->getCustomerOrderData($customerId);

        return $this->validateAggregate($orderData, $attribute, $operator, $value);
    }

    /**
     * Resolve matching customer ids for the set-resolvable positive existence attributes
     *
     * Aggregate/date attributes and every customer-level negation require the full customer
     * universe (including zero-order customers) and are therefore not resolvable set-based here.
     *
     * @return int[]|null
     */
    public function getMatchingCustomerIds(): ?array
    {
        $attribute = (string) $this->getAttribute();
        $operator = (string) $this->getOperator();

        if (!in_array($attribute, self::EXISTENCE_ATTRIBUTES, true)) {
            return null;
        }

        if (in_array($operator, self::NEGATIVE_OPERATORS, true)) {
            return null;
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $this->buildExistenceSelect($attribute, $operator, $this->getValue())
            ->reset(Select::COLUMNS)
            ->distinct(true)
            ->columns(['customer_id' => 'o.customer_id'])
            ->where('o.customer_id IS NOT NULL');

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Get aggregated order data for a customer (zero-order customers yield COALESCE-ed zeros)
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
                    'total_revenue' => new Expression('COALESCE(SUM(base_grand_total), 0)'),
                    'average_order_value' => new Expression('COALESCE(AVG(base_grand_total), 0)'),
                    'total_items' => new Expression('COALESCE(SUM(total_qty_ordered), 0)'),
                    'first_order_date' => new Expression('MIN(created_at)'),
                    'last_order_date' => new Expression('MAX(created_at)'),
                ]
            )
            ->where('customer_id = ?', $customerId)
            ->where('state NOT IN (?)', self::EXCLUDED_STATES);

        return $connection->fetchRow($select) ?: [];
    }

    /**
     * Validate an aggregate (numeric/date) attribute
     *
     * @param array $orderData
     * @param string $attribute
     * @param string $operator
     * @param mixed $value
     * @return bool
     */
    protected function validateAggregate(array $orderData, string $attribute, string $operator, mixed $value): bool
    {
        if (in_array($attribute, self::DATE_ATTRIBUTES, true)) {
            return $this->validateDate($orderData[$attribute] ?? null, $operator, $value);
        }

        $actualValue = $orderData[$attribute] ?? 0;

        if (!is_numeric($actualValue) || !is_numeric(is_array($value) ? '' : $value)) {
            if ($operator === 'between') {
                return $this->isValueBetween((float) $actualValue, $value);
            }
            return false;
        }

        $actualValue = (float) $actualValue;
        $compare = (float) $value;

        return match ($operator) {
            '==' => $actualValue == $compare,
            '!=' => $actualValue != $compare,
            '>' => $actualValue > $compare,
            '<' => $actualValue < $compare,
            '>=' => $actualValue >= $compare,
            '<=' => $actualValue <= $compare,
            'between' => $this->isValueBetween($actualValue, $value),
            default => false,
        };
    }

    /**
     * Validate a date attribute using UTC-aware comparison
     *
     * @param string|null $actual
     * @param string $operator
     * @param mixed $value
     * @return bool
     */
    protected function validateDate(?string $actual, string $operator, mixed $value): bool
    {
        if ($actual === null || $actual === '') {
            return false;
        }

        $actualTs = $this->toUtcTimestamp($actual);
        if ($actualTs === null) {
            return false;
        }

        if ($operator === 'between') {
            [$from, $to] = $this->splitRange($value);
            $fromTs = $this->toUtcTimestamp($from);
            $toTs = $this->toUtcTimestamp($to);
            if ($fromTs === null || $toTs === null) {
                return false;
            }
            return $actualTs >= $fromTs && $actualTs <= $toTs;
        }

        $compareTs = $this->toUtcTimestamp((string) $value);
        if ($compareTs === null) {
            return false;
        }

        return match ($operator) {
            '==' => gmdate('Y-m-d', $actualTs) === gmdate('Y-m-d', $compareTs),
            '!=' => gmdate('Y-m-d', $actualTs) !== gmdate('Y-m-d', $compareTs),
            '>' => $actualTs > $compareTs,
            '<' => $actualTs < $compareTs,
            default => false,
        };
    }

    /**
     * Validate an existence attribute, negating at the customer level for negative operators
     *
     * @param int $customerId
     * @param string $attribute
     * @param string $operator
     * @param mixed $value
     * @return bool
     */
    protected function validateExistence(int $customerId, string $attribute, string $operator, mixed $value): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $this->buildExistenceSelect($attribute, $operator, $value)
            ->reset(Select::COLUMNS)
            ->columns(['o.entity_id'])
            ->where('o.customer_id = ?', $customerId)
            ->limit(1);

        $exists = (bool) $connection->fetchOne($select);

        return in_array($operator, self::NEGATIVE_OPERATORS, true) ? !$exists : $exists;
    }

    /**
     * Build a select carrying the POSITIVE predicate for an existence attribute
     *
     * Negation is applied by the caller at the customer level, never per row.
     *
     * @param string $attribute
     * @param string $operator
     * @param mixed $value
     * @return Select
     */
    private function buildExistenceSelect(string $attribute, string $operator, mixed $value): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $select = $connection->select()
            ->from(['o' => $orderTable], ['o.entity_id'])
            ->where('o.state NOT IN (?)', self::EXCLUDED_STATES);

        [$column, $joinable] = $this->resolveExistenceColumn($attribute);

        if ($joinable === 'payment') {
            $paymentTable = $this->resourceConnection->getTableName('sales_order_payment');
            $select->join(['sop' => $paymentTable], 'sop.parent_id = o.entity_id', []);
        } elseif ($joinable === 'shipping_address') {
            $addressTable = $this->resourceConnection->getTableName('sales_order_address');
            $select->join(
                ['soa' => $addressTable],
                'soa.parent_id = o.entity_id AND soa.address_type = ' . $connection->quote('shipping'),
                []
            );
        }

        $this->applyPositivePredicate($select, $column, $operator, $value);

        return $select;
    }

    /**
     * Map an existence attribute to its qualified column and required join
     *
     * @param string $attribute
     * @return array{0: string, 1: string|null}
     */
    private function resolveExistenceColumn(string $attribute): array
    {
        return match ($attribute) {
            'used_coupon' => ['o.coupon_code', null],
            'order_status' => ['o.status', null],
            'shipping_method' => ['o.shipping_method', null],
            'payment_method' => ['sop.method', 'payment'],
            'shipping_country' => ['soa.country_id', 'shipping_address'],
            default => ['o.' . $attribute, null],
        };
    }

    /**
     * Apply the positive form of the operator predicate to the select
     *
     * @param Select $select
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return void
     */
    private function applyPositivePredicate(Select $select, string $column, string $operator, mixed $value): void
    {
        $values = array_map('trim', is_array($value) ? $value : explode(',', (string) $value));

        switch ($operator) {
            case '()':
            case '!()':
                $select->where($column . ' IN (?)', $values);
                break;
            case '{}':
            case '!{}':
                $select->where($column . ' LIKE ?', '%' . ($values[0] ?? '') . '%');
                break;
            case '==':
            case '!=':
            default:
                $select->where($column . ' = ?', $values[0] ?? '');
        }
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
        [$from, $to] = $this->splitRange($rangeValue);

        return $actualValue >= (float) $from && $actualValue <= (float) $to;
    }

    /**
     * Split a range value (array or comma separated string) into a [from, to] pair
     *
     * @param mixed $value
     * @return array{0: string, 1: string}
     */
    private function splitRange(mixed $value): array
    {
        if (is_array($value)) {
            return [(string) ($value[0] ?? ''), (string) ($value[1] ?? '')];
        }

        $values = explode(',', (string) $value);

        return [trim($values[0] ?? ''), trim($values[1] ?? '')];
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
