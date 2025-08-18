<?php declare(strict_types=1);

namespace Common;

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
 * @copyright Daniel Berthereau, 2018-2025
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

        $tableColumns = [
            ['fulltext_search' => 'is_public'],
            ['media' => 'ingester'],
            ['media' => 'renderer'],
            ['media' => 'media_type'],
            ['media' => 'extension'],
            ['resource' => 'resource_type'],
            ['value' => 'type'],
            ['value' => 'lang'],
            // Keep session last, because it may fail on big base.
            ['session' => 'modified'],
        ];

        $newIndices = [];
        foreach ($tableColumns as $key => $tableColumn) {
            $table = key($tableColumn);
            $column = reset($tableColumn);
            $result = $connection->executeQuery("SHOW INDEX FROM `$table` WHERE `column_name` = '$column';");
            if ($result->fetchOne()) {
                unset($tableColumns[$key]);
            } else {
                $newIndices[] = "$table/$column";
            }
        }

        if ($newIndices) {
            $message = new \Common\Stdlib\PsrMessage(
                'Some indexes will be added to tables to improve performance: {list}.', // @translate
                ['list' => implode(', ', $newIndices)]
            );
            $messenger->addWarning($message);
            $newIndices = [];
            foreach ($tableColumns as $key => $tableColumn) {
                $table = key($tableColumn);
                $column = reset($tableColumn);
                try {
                    $connection->executeStatement("ALTER TABLE `$table` ADD INDEX `$column` (`$column`);");
                    $newIndices[] = "$table/$column";
                } catch (\Exception $e) {
                    $message = new \Common\Stdlib\PsrMessage(
                        'Unable to add index "{index}" in table "{table}" to improve performance: {message}', // @translate
                        ['index' => $column, 'table' => $table, 'message' => $e->getMessage()]
                    );
                    $messenger->addError($message);
                }
            }
            if ($newIndices) {
                $message = new \Common\Stdlib\PsrMessage(
                    'Some indexes were added to tables to improve performance: {list}.', // @translate
                    ['list' => implode(', ', $newIndices)]
                );
                $messenger->addSuccess($message);
            }
        }
    }

    protected function checkGeneric(): void
    {
        $paths = glob(OMEKA_PATH . '/modules/*/src/Generic/AbstractModule.php');
        if (count($paths)) {
            return;
        }

        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $connection->executeStatement('DELETE FROM `module` WHERE `id` = "Generic";');

        if (!file_exists(OMEKA_PATH . '/modules/Generic/AbstractModule.php')) {
            return;
        }

        $message = new \Common\Stdlib\PsrMessage(
            'The module Generic is no longer needed and can be removed.' // @translate
        );
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $messenger->addWarning($message);
    }
}
