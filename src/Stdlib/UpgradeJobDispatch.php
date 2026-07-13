<?php declare(strict_types=1);

namespace Common\Stdlib;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Entity\Job;
use Omeka\Job\Dispatcher;

/**
 * Dispatch a background job during a module upgrade.
 *
 * During upgrade, the module classes are not available to the spawned
 * background process, because the module state in the database is still
 * "needs_upgrade".
 *
 * So this helper temporarily sets the module version and active flag so the
 * spawned process can bootstrap the module, waits for the job to start, then
 * restores the original active flag. The Module Manager sets the real version
 * and state once upgrade() returns.
 *
 * The module id is derived from the first segment of the job class namespace.
 * The target version is read from the module ini when not passed. The job class
 * and its traits must be passed as absolute paths in $requireFiles, because the
 * module is not autoloaded yet and Dispatcher::dispatch() checks the class
 * existence.
 *
 * Usage in a module upgrade script:
 * ```php
 * $upgradeJobDispatch = $services
 *     ->get('Common\UpgradeJobDispatch');
 * $jobDir = dirname(__DIR__, 2) . '/src/Job/'; $job = $upgradeJobDispatch(
 *     \MyModule\Job\MyJob::class,
 *     ['key' => 'value'],
 *     [$jobDir . 'MyJob.php']
 * );
 */
class UpgradeJobDispatch
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    public function __construct(ServiceLocatorInterface $services)
    {
        $this->services = $services;
    }

    /**
     * @param string $jobClass Fully qualified job class.
     * @param array $args Job arguments.
     * @param array $requireFiles Absolute paths of files to require before
     *   dispatch (the job class and its traits).
     * @param string|null $newVersion Target version to set temporarily. Read
     *   from the module ini when null.
     */
    public function __invoke(
        string $jobClass,
        array $args = [],
        array $requireFiles = [],
        ?string $newVersion = null
    ): Job {
        foreach ($requireFiles as $file) {
            if (is_string($file) && file_exists($file)) {
                require_once $file;
            }
        }

        $services = $this->services;
        $connection = $services->get('Omeka\Connection');
        $moduleId = strtok(ltrim($jobClass, '\\'), '\\');

        if ($newVersion === null) {
            $module = $services->get('Omeka\ModuleManager')
                ->getModule($moduleId);
            $newVersion = $module ? $module->getIni('version') : null;
        }

        $moduleRow = $connection->executeQuery(
            'SELECT is_active FROM module WHERE id = :id',
            ['id' => $moduleId]
        )->fetchAssociative();
        $wasActive = (bool) ($moduleRow['is_active'] ?? false);

        $connection->executeStatement(
            'UPDATE module SET version = :version, is_active = 1 WHERE id = :id',
            ['version' => $newVersion, 'id' => $moduleId]
        );

        $dispatcher = $services->get(Dispatcher::class);
        $job = $dispatcher->dispatch($jobClass, $args);

        sleep(5);

        $status = $connection->executeQuery(
            'SELECT status FROM job WHERE id = :id',
            ['id' => $job->getId()]
        )->fetchOne();
        if ($status === Job::STATUS_STARTING) {
            $services->get('ControllerPluginManager')
                ->get('messenger')
                ->addWarning(new PsrMessage(
                    'The job #{job_id} is still starting after the sleep delay. It may need to be relaunched manually.', // @translate
                    ['job_id' => $job->getId()]
                ));
        }

        if (!$wasActive) {
            $connection->executeStatement(
                'UPDATE module SET is_active = 0 WHERE id = :id',
                ['id' => $moduleId]
            );
        }

        return $job;
    }
}
