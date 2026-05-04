<?php declare(strict_types=1);

namespace Common;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Laminas\Mvc\Controller\Plugin\Url $url
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$url = $plugins->get('url');
// Api is not available when upgrading with module AdvancedSearch.
// $api = $plugins->get('api');
// $config = require dirname(__DIR__, 2) . '/config/module.config.php';
// $settings = $services->get('Omeka\Settings');
// $translate = $plugins->get('translate');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
// $entityManager = $services->get('Omeka\EntityManager');

$this->preparePsrMessage();
$this->checkExtensionIntl();
$this->fixIndexes();
$this->checkGeneric();

if (version_compare($oldVersion, '3.4.85', '<')) {
    // initDataToPopulate() now seeds array defaults (multi-checkbox,
    // multi-select) on first init via a per-module per-scope sentinel. To avoid
    // re-seeding array defaults on existing installs (where a missing row may
    // mean "user emptied the field"), pre-seed the sentinel for every (module,
    // scope, target) tuple that already has at least one setting row from that
    // module's defaults.
    /** @var \Omeka\ModuleManager\ModuleManager $moduleManager */
    $moduleManager = $services->get('Omeka\ModuleManager');
    $scopes = [
        'config' => ['table' => 'setting', 'targetCol' => null],
        'site_settings' => ['table' => 'site_setting', 'targetCol' => 'site_id'],
        'user_settings' => ['table' => 'user_setting', 'targetCol' => 'user_id'],
    ];

    $mergedConfig = $services->get('Config');
    foreach ($moduleManager->getModules() as $moduleName => $module) {
        $moduleNs = strtolower((string) $moduleName);
        $config = $mergedConfig;
        if (!isset($config[$moduleNs]) || !is_array($config[$moduleNs])) {
            $moduleFile = $module->getModuleFilePath();
            $configFile = $moduleFile ? dirname($moduleFile) . '/config/module.config.php' : null;
            if (!$configFile || !is_readable($configFile)) {
                continue;
            }
            try {
                $config = @include $configFile;
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($config)) {
                continue;
            }
        }

        foreach ($scopes as $scope => $tableSpec) {
            $defaults = $config[$moduleNs][$scope] ?? null;
            if (!is_array($defaults) || !$defaults) {
                continue;
            }
            $sentinelKey = $moduleNs . '_initialized_' . $scope;
            $defaultIds = array_keys($defaults);

            if ($tableSpec['targetCol'] === null) {
                $sql = sprintf('SELECT 1 FROM %s WHERE id IN (?) LIMIT 1', $tableSpec['table']);
                $found = $connection->executeQuery(
                    $sql,
                    [$defaultIds],
                    [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
                )->fetchOne();
                if ($found) {
                    $connection->executeStatement(
                        sprintf('INSERT IGNORE INTO %s (id, value) VALUES (?, ?)', $tableSpec['table']),
                        [$sentinelKey, json_encode('1')]
                    );
                }
                continue;
            }

            $sql = sprintf(
                'SELECT DISTINCT %s FROM %s WHERE id IN (?)',
                $tableSpec['targetCol'],
                $tableSpec['table']
            );
            $targets = $connection->executeQuery(
                $sql,
                [$defaultIds],
                [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
            )->fetchFirstColumn();

            foreach ($targets as $targetId) {
                $connection->executeStatement(
                    sprintf(
                        'INSERT IGNORE INTO %s (id, %s, value) VALUES (?, ?, ?)',
                        $tableSpec['table'],
                        $tableSpec['targetCol']
                    ),
                    [$sentinelKey, $targetId, json_encode('1')]
                );
            }
        }
    }

    $message = new \Common\Stdlib\PsrMessage(
        'The dialog template and related view helpers have been improved for accessibility (ARIA): proper heading level (h2), aria-labelledby/aria-describedby on the dialog, aria-label on close buttons, role/aria-live on messages. Modules and themes overriding this template or using its CSS classes should be reviewed and updated accordingly.' // @translate
    );
    $messenger->addWarning($message);
}
