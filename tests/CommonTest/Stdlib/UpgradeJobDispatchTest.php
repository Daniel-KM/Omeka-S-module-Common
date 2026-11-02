<?php declare(strict_types=1);

namespace CommonTest\Stdlib;

use CommonTest\AbstractTestCase;
use Omeka\Entity\Job;
use Omeka\Job\Dispatcher;

class UpgradeJobDispatchTest extends AbstractTestCase
{
    protected const MODULE = 'UpgradeJobDispatchDummy';

    protected array $jobIds = [];

    public function tearDown(): void
    {
        $connection = $this->getService('Omeka\Connection');
        $connection->executeStatement('DELETE FROM `module` WHERE `id` = ?', [self::MODULE]);
        foreach ($this->jobIds as $id) {
            $connection->executeStatement('DELETE FROM `job` WHERE `id` = ?', [$id]);
        }
        parent::tearDown();
    }

    /**
     * During install, the row of the module does not exist yet: it is created
     * for the spawned process, then removed for the Module Manager.
     */
    public function testDispatchCreatesTemporaryRowDuringInstall(): void
    {
        $rowDuringDispatch = $this->dispatchAndCaptureRow();
        $this->assertSame(['is_active' => 1, 'version' => '1.2.3'], $rowDuringDispatch);
        $this->assertFalse($this->moduleRow());
    }

    /**
     * During upgrade, the row exists: it is activated with the new version for
     * the spawned process, then its active flag is restored.
     */
    public function testDispatchRestoresInactiveRowDuringUpgrade(): void
    {
        $this->getService('Omeka\Connection')->executeStatement(
            'INSERT INTO `module` (`id`, `is_active`, `version`) VALUES (?, 0, "1.0.0")',
            [self::MODULE]
        );
        $rowDuringDispatch = $this->dispatchAndCaptureRow();
        $this->assertSame(['is_active' => 1, 'version' => '1.2.3'], $rowDuringDispatch);
        $this->assertSame(['is_active' => 0, 'version' => '1.2.3'], $this->moduleRow());
    }

    protected function dispatchAndCaptureRow(): ?array
    {
        $this->loginAsAdmin();
        $services = $this->getServiceManager();
        $entityManager = $services->get('Omeka\EntityManager');
        $rowDuringDispatch = null;
        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->method('dispatch')->willReturnCallback(
            function (string $class) use ($entityManager, &$rowDuringDispatch): Job {
                $rowDuringDispatch = $this->moduleRow() ?: null;
                $job = new Job();
                $job->setStatus(Job::STATUS_COMPLETED);
                $job->setClass($class);
                $entityManager->persist($job);
                $entityManager->flush();
                $this->jobIds[] = $job->getId();
                return $job;
            }
        );
        $services->setAllowOverride(true);
        $services->setService(Dispatcher::class, $dispatcher);
        $services->get('Common\UpgradeJobDispatch')(self::MODULE . '\Job\Dummy', [], [], '1.2.3');
        return $rowDuringDispatch;
    }

    /**
     * @return array|false
     */
    protected function moduleRow()
    {
        $row = $this->getService('Omeka\Connection')->fetchAssociative(
            'SELECT `is_active`, `version` FROM `module` WHERE `id` = ?',
            [self::MODULE]
        );
        return $row ? ['is_active' => (int) $row['is_active'], 'version' => $row['version']] : false;
    }
}
