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

        // Do not create an index if it exists, whatever its name is.
        $missingIndexes = \Common\Stdlib\DatabaseIndexes::missing($connection);
        $newIndices = array_map(fn ($entry) => $entry['table'] . '/' . $entry['index'], $missingIndexes);

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
        foreach ($missingIndexes as $entry) {
            $table = $entry['table'];
            $indexName = $entry['index'];
            $columnsSql = $entry['columns'];
            try {
                $logger->info(
                    'Adding index `{index}` on table `{table}`.', // @translate
                    ['index' => $indexName, 'table' => $table]
                );
                // Online ddl avoids to lock a big table during the process. It
                // is not supported by some engines and versions, so retry.
                $sql = "ALTER TABLE `$table` ADD INDEX `$indexName` ($columnsSql)";
                try {
                    $connection->executeStatement($sql . ', ALGORITHM=INPLACE, LOCK=NONE');
                } catch (\Throwable $e) {
                    $connection->executeStatement($sql);
                }
                $added[] = "$table/$indexName";
                $logger->info(
                    'Successfully added index `{index}` on table `{table}`.', // @translate
                    ['index' => $indexName, 'table' => $table]
                );
            } catch (\Throwable $e) {
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

        if (!$added) {
            throw new \Common\Job\Exception\RuntimeException(
                'No database index was added: all the {count} indexes failed.', // @translate
                ['count' => count($missingIndexes)]
            );
        }

        $logger->info(
            'Successfully added {count} database indexes: {list}', // @translate
            [
                'count' => count($added),
                'list' => implode(', ', $added),
            ]
        );
    }
}
