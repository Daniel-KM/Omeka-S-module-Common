<?php declare(strict_types=1);

namespace Common\Stdlib;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Entity\Job;
use Omeka\Job\Dispatcher;
use Omeka\Job\DispatchStrategy\PhpCli;

/**
 * Dispatch a background job during a module upgrade or install.
 *
 * The spawned background process loads only the modules that are active in the
 * table "module" (columns id, is_active, version). During upgrade, the row of
 * the module is still "needs_upgrade" (old version); during install, the row
 * does not exist yet, because the Module Manager creates it once install()
 * returns.
 *
 * So this helper temporarily sets the module version and active flag in this
 * row, or creates it, so the spawned process can bootstrap the module, waits
 * for the job to start, then restores the original active flag, or removes the
 * temporary row. The Module Manager sets the real version and state once
 * upgrade() or install() returns.
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
            $moduleRow
                ? 'UPDATE module SET version = :version, is_active = 1 WHERE id = :id'
                : 'INSERT INTO module (id, is_active, version) VALUES (:id, 1, :version)',
            ['version' => $newVersion, 'id' => $moduleId]
        );

        $dispatcher = $services->get(Dispatcher::class);
        $job = $dispatcher->dispatch($jobClass, $args, $this->dispatchStrategy());

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

        if (!$moduleRow) {
            $connection->executeStatement(
                'DELETE FROM module WHERE id = :id',
                ['id' => $moduleId]
            );
        } elseif (!$wasActive) {
            $connection->executeStatement(
                'UPDATE module SET is_active = 0 WHERE id = :id',
                ['id' => $moduleId]
            );
        }

        return $job;
    }

    /**
     * Get a strategy with a valid server url when the upgrade is run via cli.
     *
     * In cli, the server url has no host, so the background script cannot
     * parse it. A placeholder is used, since the real host is unknown.
     */
    protected function dispatchStrategy(): ?PhpCli
    {
        if (!$this->services->get('Omeka\Job\DispatchStrategy') instanceof PhpCli) {
            return null;
        }
        $viewHelpers = $this->services->get('ViewHelperManager');
        $serverUrl = (string) $viewHelpers->get('ServerUrl')->__invoke();
        if (parse_url($serverUrl, PHP_URL_HOST)) {
            return null;
        }
        $config = $this->services->get('Config');
        return new PhpCli(
            $this->services->get('Omeka\Cli'),
            $viewHelpers->get('BasePath')->__invoke(),
            'http://localhost',
            empty($config['cli']['phpcli_path']) ? null : $config['cli']['phpcli_path']
        );
    }
}
