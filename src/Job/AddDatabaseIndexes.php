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

        $tableColumns = [
            ['fulltext_search' => 'is_public'],
            ['media' => 'ingester'],
            ['media' => 'renderer'],
            ['media' => 'media_type'],
            ['media' => 'extension'],
            ['resource' => 'resource_type'],
            ['value' => 'type'],
            ['value' => 'lang'],
            // Keep session last, because it may fail on a big database.
            ['session' => 'modified'],
        ];

        // Do not create index if it exists, whatever the name is.
        $newIndices = [];
        foreach ($tableColumns as $key => $tableColumn) {
            $table = key($tableColumn);
            $column = reset($tableColumn);
            $stmt = $connection->executeQuery(
                "SHOW INDEX FROM `$table` WHERE `column_name` = '$column';"
            );
            $result = $stmt->fetchOne();
            if ($result) {
                unset($tableColumns[$key]);
            } else {
                $newIndices[] = "$table/$column";
            }
        }

        if (!$newIndices) {
            $logger->info('All database indexes already exist.'); // @translate
            return;
        }

        $logger->info(
            'Adding {count} database indexes: {list}', // @translate
            ['count' => count($newIndices), 'list' => implode(', ', $newIndices)]
        );

        $added = [];
        foreach ($tableColumns as $tableColumn) {
            $table = key($tableColumn);
            $column = reset($tableColumn);
            try {
                $logger->info(
                    'Adding index on table `{table}` for column `{column}`.', // @translate
                    ['table' => $table, 'column' => $column]
                );
                $connection->executeStatement(
                    "ALTER TABLE `$table` ADD INDEX `$column` (`$column`);"
                );
                $added[] = "$table/$column";
                $logger->info(
                    'Successfully added index on table `{table}` for column `{column}`.', // @translate
                    ['table' => $table, 'column' => $column]
                );
            } catch (\Exception $e) {
                $logger->err(
                    'Unable to add index on table `{table}` for column `{column}`: {msg}', // @translate
                    ['table' => $table, 'column' => $column, 'msg' => $e->getMessage()]
                );
            }
        }

        if ($added) {
            $logger->info(
                'Successfully added {count} database indexes: {list}', // @translate,
                ['count' => count($added), 'list' => implode(', ', $added)]
            );
        }
    }
}
