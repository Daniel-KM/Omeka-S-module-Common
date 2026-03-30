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
            } catch (\Exception $e) {
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
            } catch (\Exception $e) {
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
            } catch (\Exception $e) {
                // Table does not exist yet.
                unset($tableIndexes[$key]);
            }
        }

        if ($newIndices) {
            // Dispatch background job to add indexes (session and value tables
            // can be very large). Temporarily mark the module as active so the
            // background PHP-CLI process can bootstrap it.
            require_once __DIR__ . '/src/Job/AddDatabaseIndexes.php';

            $moduleId = 'Common';
            $moduleRow = $connection->executeQuery(
                'SELECT `is_active`, `version` FROM `module` WHERE `id` = ?',
                [$moduleId]
            )->fetchAssociative();
            $wasActive = (bool) ($moduleRow['is_active'] ?? false);

            // Read the new version from module.ini.
            $ini = parse_ini_file(__DIR__ . '/config/module.ini');
            $newVersion = $ini['version']
                ?? $moduleRow['version'];
            $connection->executeStatement(
                'UPDATE `module` SET `version` = ?, `is_active` = 1 WHERE `id` = ?',
                [$newVersion, $moduleId]
            );

            $dispatcher = $services->get('Omeka\Job\Dispatcher');
            $job = $dispatcher->dispatch(
                \Common\Job\AddDatabaseIndexes::class
            );

            // Wait for the background process to read the module state.
            sleep(5);

            $status = $connection->executeQuery(
                'SELECT `status` FROM `job` WHERE `id` = ?',
                [$job->getId()]
            )->fetchOne();
            if ($status === \Omeka\Entity\Job::STATUS_STARTING) {
                $messenger->addWarning(new \Common\Stdlib\PsrMessage(
                    'The job #{job_id} is still starting. It may need to be relaunched manually.', // @translate
                    ['job_id' => $job->getId()]
                ));
            }

            // Restore is_active if the module was inactive. The version is
            // not restored: the Module Manager overwrites it after upgrade.
            if (!$wasActive) {
                $connection->executeStatement(
                    'UPDATE `module`  SET `is_active` = 0 WHERE `id` = ?',
                    [$moduleId]
                );
            }

            $urlHelper = $services->get('ViewHelperManager')->get('url');
            $message = new \Common\Stdlib\PsrMessage(
                'Adding database indexes in background (job {link_job}#{job_id}{link_end}, {link_log}logs{link_end}).', // @translate
                [
                    'link_job' => sprintf('<a href="%s">', htmlspecialchars($urlHelper('admin/id', ['controller' => 'job', 'id' => $job->getId()]))),
                    'job_id' => $job->getId(),
                    'link_end' => '</a>',
                    'link_log' => class_exists('Log\Module', false)
                        ? sprintf('<a href="%1$s">', $urlHelper('admin/default', ['controller' => 'log', ], ['query' => ['job_id' => $job->getId()]]))
                        : sprintf('<a href="%1$s" target="_blank">', $urlHelper('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])),
                ]
            );
            $message->setEscapeHtml(false);
            $messenger->addSuccess($message);
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
