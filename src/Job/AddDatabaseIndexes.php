<?php declare(strict_types=1);

namespace Common\Job;

use Omeka\Job\AbstractJob;

/**
 * Add indices to speed up omeka.
 */
class AddDatabaseIndexes extends AbstractJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $logger = $services->get('Omeka\Logger');
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('common/add-database-indexes/job_' . $this->job->getId());
        $logger->addProcessor($referenceIdProcessor);

        // Simple: ['table' => 'column'].
        // Composite: ['table' => ['idx_name' => 'sql']].
        $tableIndexes = [
            ['fulltext_search' => 'is_public'],
            ['media' => 'ingester'],
            ['media' => 'renderer'],
            ['media' => 'media_type'],
            ['media' => 'extension'],
            ['resource' => 'resource_type'],
            ['resource' => ['idx_type_created' => '`resource_type`, `created`']],
            ['resource' => ['idx_type_modified' => '`resource_type`, `modified`']],
            ['value' => 'type'],
            ['value' => 'lang'],
            ['value' => ['idx_property_value' => '`property_id`, `value`(190)']],
            // Keep session last, because it may fail on a big database.
            ['session' => 'modified'],
        ];

        // Do not create index if it exists, whatever the name is.
        $newIndices = [];
        foreach ($tableIndexes as $key => $tableIndex) {
            $table = key($tableIndex);
            $columns = reset($tableIndex);
            if (is_array($columns)) {
                $indexName = key($columns);
                $checkSql = "SHOW INDEX FROM `$table` WHERE `Key_name` = '$indexName'";
            } else {
                $indexName = $columns;
                $checkSql = "SHOW INDEX FROM `$table` WHERE `Column_name` = '$columns'";
            }
            $result = $connection
                ->executeQuery($checkSql)->fetchOne();
            if ($result) {
                unset($tableIndexes[$key]);
            } else {
                $newIndices[] = "$table/$indexName";
            }
        }

        if (!$newIndices) {
            $logger->info(
                'All database indexes already exist.' // @translate
            );
            return;
        }

        $logger->info(
            'Adding {count} database indexes: {list}', // @translate
            [
                'count' => count($newIndices),
                'list' => implode(', ', $newIndices),
            ]
        );

        $added = [];
        foreach ($tableIndexes as $tableIndex) {
            $table = key($tableIndex);
            $columns = reset($tableIndex);
            if (is_array($columns)) {
                $indexName = key($columns);
                $columnsSql = reset($columns);
            } else {
                $indexName = $columns;
                $columnsSql = "`$columns`";
            }
            try {
                $logger->info(
                    'Adding index `{index}` on table `{table}`.', // @translate
                    ['index' => $indexName, 'table' => $table]
                );
                $connection->executeStatement(
                    "ALTER TABLE `$table`"
                    . " ADD INDEX `$indexName`"
                    . " ($columnsSql)"
                );
                $added[] = "$table/$indexName";
                $logger->info(
                    'Successfully added index `{index}` on table `{table}`.', // @translate
                    ['index' => $indexName, 'table' => $table]
                );
            } catch (\Exception $e) {
                $logger->err(
                    'Unable to add index `{index}` on table `{table}`: {msg}', // @translate
                    [
                        'index' => $indexName,
                        'table' => $table,
                        'msg' => $e->getMessage(),
                    ]
                );
            }
        }

        if ($added) {
            $logger->info(
                'Successfully added {count} database indexes: {list}', // @translate
                [
                    'count' => count($added),
                    'list' => implode(', ', $added),
                ]
            );
        }
    }
}
