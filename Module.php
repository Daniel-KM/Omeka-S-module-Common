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
 * This module is useless alone: it is designed to be used by other module.
 * See readme.
 *
 * @copyright Daniel Berthereau, 2018-2024
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    public function getConfig()
    {
        return require __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $services)
    {
        $this->fixIndexes($services);
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $services)
    {
        $filepath = __DIR__ . '/data/scripts/upgrade.php';
        $this->setServiceLocator($services);
        require_once $filepath;
    }

    /**
     * Early fix media_type index.
     *
     * See migration 20240219000000_AddIndexMediaType.
     */
    protected function fixIndexes(ServiceLocatorInterface $services)
    {
        // Early fix media_type index.
        // See migration 20240219000000_AddIndexMediaType.
        $connection = $services->get('Omeka\Connection');
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        try {
            $connection->executeStatement('ALTER TABLE `media` CHANGE `media_type` `media_type` varchar(190) COLLATE "utf8mb4_unicode_ci" NULL AFTER `source`;');
            $connection->executeStatement('ALTER TABLE `media` ADD INDEX `media_type` (`media_type`);');
            $message = new \Common\Stdlib\PsrMessage(
                'An index has been added to media types to improve performance.' // @translate
            );
            $messenger->addSuccess($message);
        } catch (\Exception $e) {
            // Index exists.
        }
    }
}
