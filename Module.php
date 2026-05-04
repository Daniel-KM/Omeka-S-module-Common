<?php declare(strict_types=1);

namespace Common;

// Polyfill Omeka\Stdlib\PsrMessage for Omeka S < 4.2 so that Common\Stdlib\PsrMessage
// can extend it and session objects serialized as PsrMessage can be
// deserialized on any version.
if (!class_exists('Omeka\Stdlib\PsrMessage', false)
    && !class_exists('Omeka\Stdlib\PsrMessage')
) {
    require_once __DIR__ . '/data/compat/MessageInterface.php';
    require_once __DIR__ . '/data/compat/PsrInterpolateInterface.php';
    require_once __DIR__ . '/data/compat/PsrInterpolateTrait.php';
    require_once __DIR__ . '/data/compat/PsrMessage.php';
}

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Module\AbstractModule;

/**
 * Common module.
 *
 * Manage in one module all features that are commonly needed in other modules
 * but that are not available in the core.
 *
 * It bring together all one-time methods used to install or to config another
 * module. It replaces previous modules Generic, Next, and various controller
 * plugins and view helpers from many modules.
 *
 * This module is useless alone: it is designed to be used by other modules.
 * See readme.
 *
 * @copyright Daniel Berthereau, 2018-2026
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    public function getConfig()
    {
        return require __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $services): void
    {
        $this->setServiceLocator($services);
        $this->preparePsrMessage();
        $this->checkExtensionIntl();
        $this->fixIndexes();
        $this->checkGeneric();
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $services): void
    {
        $this->setServiceLocator($services);
        $filepath = __DIR__ . '/data/scripts/upgrade.php';
        require_once $filepath;
    }

    /**
     * Load files required to use PsrMessage.
     */
    protected function preparePsrMessage(): void
    {
        // Polyfill core PsrMessage classes for Omeka S < 4.2.
        if (version_compare(\Omeka\Module::VERSION, '4.2', '<')) {
            require_once __DIR__ . '/data/compat/MessageInterface.php';
            require_once __DIR__ . '/data/compat/PsrInterpolateInterface.php';
            require_once __DIR__ . '/data/compat/PsrInterpolateTrait.php';
            require_once __DIR__ . '/data/compat/PsrMessage.php';
        }
        require_once __DIR__ . '/src/Stdlib/PsrInterpolateInterface.php';
        require_once __DIR__ . '/src/Stdlib/PsrInterpolateTrait.php';
        require_once __DIR__ . '/src/Stdlib/PsrMessage.php';
    }

    protected function checkExtensionIntl(): void
    {
        if (!extension_loaded('intl')) {
            /** @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger */
            $services = $this->getServiceLocator();
            $messenger = $services->get('ControllerPluginManager')->get('messenger');
            $messenger->addWarning(
                'The php extension "intl" is not available. It is recommended to install it to manage diacritics and non-latin characters and to translate dates, numbers and more.' // @translate
            );
        }
    }

    /**
     * Early fix media_type, ingester and renderer indexes.
     *
     * See migration 20240219000000_AddIndexMediaType.
     */
    protected function fixIndexes(): void
    {
        /**
         * @var \Doctrine\DBAL\Connection $connection
         * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
         */
        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $messenger = $services->get('ControllerPluginManager')->get('messenger');

        // Early fix media_type index and other common indexes.
        // See migration 20240219000000_AddIndexMediaType.
        $sqls = <<<'SQL'
            ALTER TABLE `asset`
            CHANGE `media_type` `media_type` varchar(190) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `name`,
            CHANGE `extension` `extension` varchar(190) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `storage_id`
            ;
            ALTER TABLE `job`
            CHANGE `pid` `pid` varchar(190) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `owner_id`,
            CHANGE `status` `status` varchar(190) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `pid`,
            CHANGE `class` `class` varchar(190) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `status`
            ;
            ALTER TABLE `media`
            CHANGE `ingester` `ingester` varchar(190) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `item_id`,
            CHANGE `renderer` `renderer` varchar(190) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `ingester`,
            CHANGE `media_type` `media_type` varchar(190) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `source`,
            CHANGE `extension` `extension` varchar(190) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `storage_id`
            ;
            ALTER TABLE `module`
            CHANGE `version` `version` varchar(190) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `is_active`
            ;
            ALTER TABLE `resource`
            CHANGE `resource_type` `resource_type` varchar(190) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `modified`
            ;
            ALTER TABLE `resource_template_property`
            CHANGE `default_lang` `default_lang` varchar(190) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `is_private`
            ;
            ALTER TABLE `value`
            CHANGE `type` `type` varchar(190) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `value_resource_id`,
            CHANGE `lang` `lang` varchar(190) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `type`
            ;
            SQL;
        foreach (explode(";\n", $sqls) as $sql) {
            try {
                $connection->executeStatement($sql);
            } catch (\Throwable $e) {
                // Already done.
            }
        }

        if (version_compare(\Omeka\Module::VERSION, '4.1', '>=')) {
            $sql = <<<'SQL'
                ALTER TABLE `site_page`
                CHANGE `layout` `layout` varchar(190) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `modified`;
                SQL;
            try {
                $connection->executeStatement($sql);
            } catch (\Throwable $e) {
                // Already done.
            }
        }

        // Add indices to speed up omeka.
        // - simple: ['table' => 'column'];
        // - composite: ['table' => ['idx_name' => 'sql']].

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
            ['item_site' => ['idx_site_item' => '`site_id`, `item_id`']],
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
            try {
                $result = $connection
                    ->executeQuery($checkSql)->fetchOne();
                if ($result) {
                    unset($tableIndexes[$key]);
                } else {
                    $newIndices[] = "$table/$indexName";
                }
            } catch (\Throwable $e) {
                // Table does not exist yet.
                unset($tableIndexes[$key]);
            }
        }

        if ($newIndices) {
            // Strategy: classify each missing index as "small" or "large" by
            // table size, then: - small: ALTER TABLE inline, immediate
            // messenger feedback; - large: aggregated into ONE detached PHP CLI
            // script call
            //   that processes them sequentially with online DDL
            //   (ALGORITHM=INPLACE, LOCK=NONE). The standalone script bypasses
            //   Omeka bootstrap and module autoload (direct PDO +
            //   database.ini), so no class-loading race whatever the module
            //   state.
            //
            // Threshold avoids paying fork+bootstrap cost for trivial ALTERs
            // while keeping the admin request responsive on multi-million-row
            // tables (mdb: tens of M values, ~1M resources; busy sites: huge
            // session table from crawlers).
            $largeRowThreshold = 100000;

            $tableNames = array_unique(array_map(fn ($t) => key($t), $tableIndexes));
            $tableRows = [];
            try {
                $rs = $connection->executeQuery(
                    'SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES'
                    . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?)',
                    [$tableNames],
                    [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
                )->fetchAllAssociative();
                foreach ($rs as $row) {
                    $tableRows[$row['TABLE_NAME']] = (int) $row['TABLE_ROWS'];
                }
            } catch (\Throwable $e) {
                // information_schema not readable: assume all small.
            }

            $small = [];
            $large = [];
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
                $entry = ['table' => $table, 'index' => $indexName, 'columns' => $columnsSql];
                if (($tableRows[$table] ?? 0) >= $largeRowThreshold) {
                    $large[] = $entry;
                } else {
                    $small[] = $entry;
                }
            }

            $added = [];
            $failed = [];
            foreach ($small as $entry) {
                $sql = sprintf(
                    'ALTER TABLE `%s` ADD INDEX `%s` (%s)',
                    $entry['table'], $entry['index'], $entry['columns']
                );
                try {
                    $connection->executeStatement($sql);
                    $added[] = $entry['table'] . '/' . $entry['index'];
                } catch (\Throwable $e) {
                    $failed[] = $entry['table'] . '/' . $entry['index'] . ': ' . $e->getMessage();
                }
            }
            if ($added) {
                $messenger->addSuccess(new \Common\Stdlib\PsrMessage(
                    'Added {count} database indexes: {list}.', // @translate
                    ['count' => count($added), 'list' => implode(', ', $added)]
                ));
            }
            if ($failed) {
                $messenger->addWarning(new \Common\Stdlib\PsrMessage(
                    'Failed to add {count} database indexes: {list}.', // @translate
                    ['count' => count($failed), 'list' => implode(' | ', $failed)]
                ));
            }

            if ($large) {
                /** @var \Omeka\Stdlib\Cli $cli */
                $cli = $services->get('Omeka\Cli');
                $phpPath = $cli->getCommandPath('php');
                $script = __DIR__ . '/data/scripts/add-database-index.php';
                $batchDir = OMEKA_PATH . '/logs';
                if (!is_dir($batchDir) || !is_writable($batchDir)) {
                    $batchDir = sys_get_temp_dir();
                }
                $batchFile = $batchDir . '/common-add-index-' . uniqid() . '.json';
                @file_put_contents($batchFile, json_encode($large));

                $largeList = implode(', ', array_map(
                    fn ($e) => $e['table'] . '/' . $e['index'] . ' (' . ($tableRows[$e['table']] ?? '?') . ' rows)',
                    $large
                ));

                if (!$phpPath || !is_readable($script) || !file_exists($batchFile)) {
                    $manualSqls = implode("\n", array_map(
                        fn ($e) => sprintf(
                            'ALTER TABLE `%s` ADD INDEX `%s` (%s), ALGORITHM=INPLACE, LOCK=NONE;',
                            $e['table'], $e['index'], $e['columns']
                        ),
                        $large
                    ));
                    @unlink($batchFile);
                    $messenger->addWarning(new \Common\Stdlib\PsrMessage(
                        'PHP-CLI unavailable for {count} large-table indexes ({list}). Ask your DBA to run: {sql}', // @translate
                        ['count' => count($large), 'list' => $largeList, 'sql' => $manualSqls]
                    ));
                } else {
                    $command = sprintf(
                        '%s %s --batch %s > /dev/null 2>&1 &',
                        escapeshellcmd($phpPath),
                        escapeshellarg($script),
                        escapeshellarg($batchFile)
                    );
                    $cli->execute($command);
                    $messenger->addSuccess(new \Common\Stdlib\PsrMessage(
                        '{count} large-table indexes are being added in background with online DDL ({list}). Track progress in logs/common-add-index.log.', // @translate
                        ['count' => count($large), 'list' => $largeList]
                    ));
                }
            }
        }
    }

    protected function checkGeneric(): void
    {
        $moduleDirs = [
            OMEKA_PATH . '/modules',
            OMEKA_PATH . '/composer-addons/modules',
        ];
        $hasGenericUsage = false;
        foreach ($moduleDirs as $dir) {
            if (is_dir($dir) && glob($dir . '/*/src/Generic/AbstractModule.php')) {
                $hasGenericUsage = true;
                break;
            }
        }
        if ($hasGenericUsage) {
            return;
        }

        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $connection->executeStatement('DELETE FROM `module` WHERE `id` = "Generic";');

        if (!file_exists(OMEKA_PATH . '/modules/Generic/AbstractModule.php')
            && !file_exists(OMEKA_PATH . '/composer-addons/modules/Generic/AbstractModule.php')
        ) {
            return;
        }

        $message = new \Common\Stdlib\PsrMessage(
            'The module Generic is no longer needed and can be removed.' // @translate
        );
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $messenger->addWarning($message);
    }
}
