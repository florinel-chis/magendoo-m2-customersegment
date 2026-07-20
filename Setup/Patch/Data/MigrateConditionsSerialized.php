<?php
/**
 * Magendoo CustomerSegment - migrate legacy serialized conditions to the canonical tree shape
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Magendoo\CustomerSegment\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Rewrites conditions_serialized rows stored in the broken numeric-key shape into the canonical tree.
 *
 * Older releases persisted combine children under numeric position keys ("1", "2", ...) with no
 * "conditions" key, e.g. {"type":"...Combine","aggregator":"all","value":"1","new_child":"",
 * "1":{...leaf...}}. Magento's Combine::loadArray()/SegmentManagement::loadConditions() read children
 * ONLY from the "conditions" list, so those trees loaded as EMPTY combines and matched every customer.
 * This patch recursively moves numeric-key children under "conditions", drops the transient "new_child"
 * marker, and leaves leaves untouched. It is idempotent: already-canonical rows and empty {} are
 * skipped without a write.
 */
class MigrateConditionsSerialized implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('magendoo_customer_segment');

        $select = $connection->select()->from($table, ['segment_id', 'conditions_serialized']);

        foreach ($connection->fetchAll($select) as $row) {
            $serialized = $row['conditions_serialized'] ?? null;
            if (!is_string($serialized) || trim($serialized) === '' || trim($serialized) === '{}') {
                continue;
            }

            $decoded = json_decode($serialized, true);
            if (!is_array($decoded) || $decoded === [] || !$this->treeNeedsMigration($decoded)) {
                continue;
            }

            $encoded = json_encode($this->normalizeNode($decoded));
            if ($encoded === false) {
                continue;
            }

            $connection->update(
                $table,
                ['conditions_serialized' => $encoded],
                ['segment_id = ?' => (int) $row['segment_id']]
            );
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    /**
     * Whether any node in the tree carries a numeric child key or the transient "new_child" marker.
     *
     * @param array $node
     * @return bool
     */
    private function treeNeedsMigration(array $node): bool
    {
        foreach ($node as $key => $value) {
            if ($key === 'new_child') {
                return true;
            }
            if (is_int($key) || (is_string($key) && ctype_digit($key))) {
                return true;
            }
            if ($key === 'conditions' && is_array($value)) {
                foreach ($value as $child) {
                    if (is_array($child) && $this->treeNeedsMigration($child)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Recursively rewrite a node: numeric-key children and existing "conditions" merge into one list.
     *
     * @param array $node
     * @return array
     */
    private function normalizeNode(array $node): array
    {
        $result = [];
        $children = [];
        $hasConditionsKey = false;

        foreach ($node as $key => $value) {
            if ($key === 'new_child') {
                continue;
            }
            if ($key === 'conditions') {
                $hasConditionsKey = true;
                if (is_array($value)) {
                    foreach ($value as $child) {
                        $children[] = $child;
                    }
                }
                continue;
            }
            if (is_int($key) || (is_string($key) && ctype_digit($key))) {
                $children[] = $value;
                continue;
            }
            $result[$key] = $value;
        }

        $type = isset($node['type']) && is_string($node['type']) ? $node['type'] : '';
        $isCombine = $hasConditionsKey
            || $children !== []
            || isset($node['aggregator'])
            || str_contains($type, 'Combine');

        if ($isCombine) {
            $normalizedChildren = [];
            foreach ($children as $child) {
                if (is_array($child)) {
                    $normalizedChildren[] = $this->normalizeNode($child);
                }
            }
            $result['conditions'] = $normalizedChildren;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
