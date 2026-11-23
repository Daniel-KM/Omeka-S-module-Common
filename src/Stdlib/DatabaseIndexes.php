<?php declare(strict_types=1);

namespace Common\Stdlib;

use Doctrine\DBAL\Connection;

/**
 * Indexes added to the tables of Omeka to speed up the queries.
 */
class DatabaseIndexes
{
    /**
     * List of indexes.
     *
     * - simple: ['table' => 'column'];
     * - composite: ['table' => ['idx_name' => 'sql']].
     *
     * Keep session last, because it may fail on a big database.
     */
    const INDEXES = [
        ['fulltext_search' => 'is_public'],
        ['media' => 'ingester'],
        ['media' => 'renderer'],
        ['media' => 'media_type'],
        ['media' => 'extension'],
        ['resource' => 'resource_type'],
        ['resource' => ['idx_type_created' => '`resource_type`, `created`']],
        ['resource' => ['idx_type_modified' => '`resource_type`, `modified`']],
        ['item_site' => ['idx_site_item' => '`site_id`, `item_id`']],
        // Speed searches and references.
        ['value' => 'type'],
        ['value' => 'lang'],
        ['value' => ['idx_property_value' => '`property_id`, `value`(190)']],
        ['value' => ['idx_property_value_resource' => '`property_id`, `value_resource_id`']],
        ['session' => 'modified'],
    ];

    /**
     * Get the missing indexes, whatever the name of the existing ones.
     *
     * The tables that don't exist are skipped.
     *
     * @return array List of arrays with keys "table", "index" and "columns".
     */
    public static function missing(Connection $connection): array
    {
        $missing = [];
        foreach (self::INDEXES as $tableIndex) {
            $table = key($tableIndex);
            $columns = reset($tableIndex);
            if (is_array($columns)) {
                $indexName = key($columns);
                $columnsSql = reset($columns);
                $checkSql = "SHOW INDEX FROM `$table` WHERE `Key_name` = '$indexName'";
            } else {
                $indexName = $columns;
                $columnsSql = "`$columns`";
                $checkSql = "SHOW INDEX FROM `$table` WHERE `Column_name` = '$columns'";
            }
            try {
                if ($connection->executeQuery($checkSql)->fetchOne()) {
                    continue;
                }
            } catch (\Throwable $e) {
                // Table does not exist yet.
                continue;
            }
            $missing[] = ['table' => $table, 'index' => $indexName, 'columns' => $columnsSql];
        }
        return $missing;
    }
}
