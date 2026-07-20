<?php
/**
 * Magendoo CustomerSegment - drop legacy duplicate indexes and foreign keys
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Magendoo\CustomerSegment\Setup\Patch\Schema;

use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Idempotently removes stale, auto-named duplicate indexes/foreign keys created by older releases.
 *
 * Earlier versions shipped a db_schema_whitelist.json that listed extra index and FK names while
 * db_schema.xml declared different objects. On installs upgraded from those versions the database
 * can carry stray duplicate indexes on is_active/refresh_mode/(segment_id, customer_id)/customer_id
 * and duplicate foreign keys. This patch drops anything present on the module tables that is NOT one
 * of the objects the current db_schema.xml produces.
 *
 * IMPORTANT: the KEEP lists below hold the PHYSICAL object names Magento's declarative-schema name
 * resolver actually creates in the database (e.g. MAGENDOO_CUSTOMER_SEGMENT_IS_ACTIVE for the
 * is_active index, FK_<md5> or a shortened MAGENDOO_CSTR_... name for foreign keys) — these are the
 * names returned by getIndexList()/getForeignKeys() and recorded in db_schema_whitelist.json. They
 * are NOT the referenceIds from db_schema.xml, which the resolver does not use for physical names.
 * Keying on the referenceIds would make this patch drop every real object. The names are
 * deterministic for a given table/column layout, so they are identical on every install of this
 * schema. It is safe on clean installs (nothing stray is present) and safe to re-run.
 */
class DropLegacyDuplicateIndexes implements SchemaPatchInterface
{
    /**
     * Physical index names Magento creates for the current db_schema.xml, keyed by table. Unique
     * constraints and FK-backing indexes also surface via getIndexList(), so they are kept here too.
     * PRIMARY is always preserved.
     *
     * @var array<string, string[]>
     */
    private const KEEP_INDEXES = [
        'magendoo_customer_segment' => [
            'PRIMARY',
            'MAGENDOO_CUSTOMER_SEGMENT_IS_ACTIVE',
            'MAGENDOO_CUSTOMER_SEGMENT_REFRESH_MODE',
        ],
        'magendoo_customer_segment_customer' => [
            'PRIMARY',
            'MAGENDOO_CUSTOMER_SEGMENT_CUSTOMER_SEGMENT_ID_CUSTOMER_ID',
            'MAGENDOO_CUSTOMER_SEGMENT_CUSTOMER_SEGMENT_ID',
            'MAGENDOO_CUSTOMER_SEGMENT_CUSTOMER_CUSTOMER_ID',
            'FK_38387B17B5970F704DF2CC9F26D519BB',
            'MAGENDOO_CSTR_SEGMENT_CSTR_CSTR_ID_CSTR_ENTT_ENTT_ID',
        ],
        'magendoo_customer_segment_log' => [
            'PRIMARY',
            'MAGENDOO_CUSTOMER_SEGMENT_LOG_SEGMENT_ID',
            'MAGENDOO_CUSTOMER_SEGMENT_LOG_ACTION',
            'MAGENDOO_CUSTOMER_SEGMENT_LOG_CREATED_AT',
            'FK_5C55A06134907E8D937E880DB9F66629',
        ],
    ];

    /**
     * Physical foreign key names Magento creates for the current db_schema.xml, keyed by table.
     *
     * @var array<string, string[]>
     */
    private const KEEP_FOREIGN_KEYS = [
        'magendoo_customer_segment' => [],
        'magendoo_customer_segment_customer' => [
            'FK_38387B17B5970F704DF2CC9F26D519BB',
            'MAGENDOO_CSTR_SEGMENT_CSTR_CSTR_ID_CSTR_ENTT_ENTT_ID',
        ],
        'magendoo_customer_segment_log' => [
            'FK_5C55A06134907E8D937E880DB9F66629',
        ],
    ];

    /**
     * @param SchemaSetupInterface $schemaSetup
     */
    public function __construct(
        private readonly SchemaSetupInterface $schemaSetup
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->schemaSetup->startSetup();
        $connection = $this->schemaSetup->getConnection();

        foreach (self::KEEP_INDEXES as $tableName => $keepIndexes) {
            $table = $this->schemaSetup->getTable($tableName);
            if (!$connection->isTableExists($table)) {
                continue;
            }

            $keepForeignKeys = self::KEEP_FOREIGN_KEYS[$tableName] ?? [];

            // Drop stray foreign keys first so their backing indexes become removable.
            foreach ($connection->getForeignKeys($table) as $foreignKey) {
                $fkName = $foreignKey['FK_NAME'] ?? '';
                if ($fkName !== '' && !in_array($fkName, $keepForeignKeys, true)) {
                    $connection->dropForeignKey($table, $fkName);
                }
            }

            // Drop stray indexes (never PRIMARY, never a declared referenceId).
            foreach ($connection->getIndexList($table) as $index) {
                $indexName = $index['KEY_NAME'] ?? '';
                if ($indexName === '' || $indexName === 'PRIMARY') {
                    continue;
                }
                if (!in_array($indexName, $keepIndexes, true)) {
                    $connection->dropIndex($table, $indexName);
                }
            }
        }

        $this->schemaSetup->endSetup();

        return $this;
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
