<?php
/**
 * Magendoo CustomerSegment SQL Builder
 *
 * Builds efficient SQL queries for batch customer segment evaluation
 * to address N+1 query performance issues.
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

class SqlBuilder
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Build a batch query to find customers matching a condition
     *
     * @param array $customerIds
     * @param string $attribute
     * @param string $operator
     * @param mixed $value
     * @param string $entityType
     * @return Select
     */
    public function buildBatchValidationQuery(
        array $customerIds,
        string $attribute,
        string $operator,
        mixed $value,
        string $entityType = 'customer'
    ): Select {
        $connection = $this->resourceConnection->getConnection();

        return match ($entityType) {
            'customer' => $this->buildCustomerAttributeQuery($connection, $customerIds, $attribute, $operator, $value),
            'order' => $this->buildOrderAggregateQuery($connection, $customerIds, $attribute, $operator, $value),
            default => $this->buildEmptyQuery($connection),
        };
    }

    /**
     * Build query for customer attribute validation
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param array $customerIds
     * @param string $attribute
     * @param string $operator
     * @param mixed $value
     * @return Select
     */
    protected function buildCustomerAttributeQuery(
        $connection,
        array $customerIds,
        string $attribute,
        string $operator,
        mixed $value
    ): Select {
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $eavAttributeTable = $this->resourceConnection->getTableName('eav_attribute');
        $customerVarcharTable = $this->resourceConnection->getTableName('customer_entity_varchar');
        $customerDatetimeTable = $this->resourceConnection->getTableName('customer_entity_datetime');
        $customerIntTable = $this->resourceConnection->getTableName('customer_entity_int');

        // Map common attributes to their storage types
        $attributeTypeMap = [
            'email' => 'static',
            'firstname' => 'varchar',
            'lastname' => 'varchar',
            'dob' => 'datetime',
            'gender' => 'int',
            'group_id' => 'int',
            'website_id' => 'static',
            'store_id' => 'static',
            'created_at' => 'static',
        ];

        $type = $attributeTypeMap[$attribute] ?? 'varchar';

        // Build the select
        $select = $connection->select()
            ->from(['c' => $customerTable], ['customer_id' => 'entity_id'])
            ->where('c.entity_id IN (?)', $customerIds);

        // Add attribute condition based on type
        $condition = $this->translateOperatorToSql($operator, $value);

        switch ($type) {
            case 'static':
                $select->where("c.{$attribute} " . $this->buildWhereClause($operator), $value);
                break;
            case 'varchar':
                $select->join(
                    ['cev' => $customerVarcharTable],
                    'c.entity_id = cev.entity_id',
                    []
                )->join(
                    ['ea' => $eavAttributeTable],
                    "cev.attribute_id = ea.attribute_id AND ea.attribute_code = '{$attribute}'",
                    []
                )->where('cev.value ' . $this->buildWhereClause($operator), $value);
                break;
            case 'datetime':
                $select->join(
                    ['ced' => $customerDatetimeTable],
                    'c.entity_id = ced.entity_id',
                    []
                )->join(
                    ['ead' => $eavAttributeTable],
                    "ced.attribute_id = ead.attribute_id AND ead.attribute_code = '{$attribute}'",
                    []
                )->where('ced.value ' . $this->buildWhereClause($operator), $value);
                break;
            case 'int':
                $select->join(
                    ['cei' => $customerIntTable],
                    'c.entity_id = cei.entity_id',
                    []
                )->join(
                    ['eai' => $eavAttributeTable],
                    "cei.attribute_id = eai.attribute_id AND eai.attribute_code = '{$attribute}'",
                    []
                )->where('cei.value ' . $this->buildWhereClause($operator), $value);
                break;
        }

        return $select;
    }

    /**
     * Build query for order aggregate validation
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param array $customerIds
     * @param string $attribute
     * @param string $operator
     * @param mixed $value
     * @return Select
     */
    protected function buildOrderAggregateQuery(
        $connection,
        array $customerIds,
        string $attribute,
        string $operator,
        mixed $value
    ): Select {
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $aggregateMap = [
            'total_orders' => 'COUNT(*)',
            'total_revenue' => 'SUM(base_grand_total)',
            'average_order_value' => 'AVG(base_grand_total)',
            'total_items' => 'SUM(total_qty_ordered)',
            'first_order_date' => 'MIN(created_at)',
            'last_order_date' => 'MAX(created_at)',
        ];

        if (!isset($aggregateMap[$attribute])) {
            return $this->buildEmptyQuery($connection);
        }

        $select = $connection->select()
            ->from(
                ['o' => $orderTable],
                ['customer_id' => 'customer_id']
            )
            ->where('o.customer_id IN (?)', $customerIds)
            ->where('o.state NOT IN (?)', ['canceled', 'closed'])
            ->group('o.customer_id');

        // Add having clause for aggregate condition
        $aggregateExpr = $aggregateMap[$attribute];
        $havingClause = $this->buildHavingClause($operator, $aggregateExpr, $value);
        $select->having($havingClause);

        return $select;
    }

    /**
     * Build empty query (returns no results)
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return Select
     */
    protected function buildEmptyQuery($connection): Select
    {
        return $connection->select()->from('customer_entity')->where('1=0');
    }

    /**
     * Translate operator to SQL where clause
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
            default => ['eq' => $value],
        };
    }

    /**
     * Build WHERE clause from operator
     *
     * @param string $operator
     * @return string
     */
    protected function buildWhereClause(string $operator): string
    {
        return match ($operator) {
            '==' => '= ?',
            '!=' => '!= ?',
            '>' => '> ?',
            '<' => '< ?',
            '>=' => '>= ?',
            '<=' => '<= ?',
            '{}' => 'LIKE ?',
            '!{}' => 'NOT LIKE ?',
            '^=' => 'LIKE ?',
            '$=' => 'LIKE ?',
            default => '= ?',
        };
    }

    /**
     * Build HAVING clause for aggregate queries
     *
     * @param string $operator
     * @param string $aggregateExpr
     * @param mixed $value
     * @return string
     */
    protected function buildHavingClause(string $operator, string $aggregateExpr, mixed $value): string
    {
        $escapedValue = is_numeric($value) ? $value : "'" . addslashes($value) . "'";

        return match ($operator) {
            '==' => "{$aggregateExpr} = {$escapedValue}",
            '!=' => "{$aggregateExpr} != {$escapedValue}",
            '>' => "{$aggregateExpr} > {$escapedValue}",
            '<' => "{$aggregateExpr} < {$escapedValue}",
            '>=' => "{$aggregateExpr} >= {$escapedValue}",
            '<=' => "{$aggregateExpr} <= {$escapedValue}",
            default => "{$aggregateExpr} = {$escapedValue}",
        };
    }

    /**
     * Validate multiple customers in a single query
     *
     * @param array $customerIds
     * @param string $attribute
     * @param string $operator
     * @param mixed $value
     * @param string $entityType
     * @return array Array of matching customer IDs
     */
    public function validateBatch(
        array $customerIds,
        string $attribute,
        string $operator,
        mixed $value,
        string $entityType = 'customer'
    ): array {
        $connection = $this->resourceConnection->getConnection();
        $select = $this->buildBatchValidationQuery($customerIds, $attribute, $operator, $value, $entityType);

        $result = $connection->fetchCol($select);
        return array_map('intval', $result);
    }
}
