<?php declare(strict_types=1);

namespace CommonTest\Job;

use Common\Job\Dispatcher;
use CommonTest\AbstractHttpControllerTestCase;
use Laminas\EventManager\Event;
use Omeka\Entity\Job;
use Omeka\Job\DispatchStrategy\StrategyInterface;

/**
 * Tests of the job dispatcher and of its events.
 */
class DispatcherTest extends AbstractHttpControllerTestCase
{
    /**
     * @var int[]
     */
    protected $jobIds = [];

    /**
     * @var array
     */
    protected $listeners = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    public function tearDown(): void
    {
        $services = $this->getApplicationServiceLocator();
        $shared = $services->get('SharedEventManager');
        foreach ($this->listeners as [$event, $listener]) {
            $shared->detach($listener, \Omeka\Job\Dispatcher::class, $event);
        }
        if ($this->jobIds) {
            $services->get('Omeka\Connection')
                ->executeStatement('DELETE FROM job WHERE id IN (' . implode(',', $this->jobIds) . ')');
        }
        parent::tearDown();
    }

    public function testServiceIsTheDispatcherWithEvents(): void
    {
        $this->assertInstanceOf(Dispatcher::class, $this->dispatcher());
    }

    public function testDispatchedJobIsManagedAndPendingEntitiesAreNotFlushed(): void
    {
        $entityManager = $this->getApplicationServiceLocator()->get('Omeka\EntityManager');
        $vocabulary = new \Omeka\Entity\Vocabulary();
        $vocabulary->setNamespaceUri('https://example.org/dispatcher-test#');
        $vocabulary->setPrefix('dispatchertest');
        $vocabulary->setLabel('Dispatcher test');
        $entityManager->persist($vocabulary);

        $job = $this->dispatchJob();

        $this->assertTrue($entityManager->contains($job));
        $this->assertSame(Job::STATUS_STARTING, $job->getStatus());
        $this->assertNull($vocabulary->getId());
        $entityManager->detach($vocabulary);
    }

    public function testListenerReturningAJobAvoidsANewJob(): void
    {
        $existing = $this->dispatchJob();
        $this->listen('job.dispatch.pre', fn (Event $event) => $existing);
        $connection = $this->getApplicationServiceLocator()->get('Omeka\Connection');
        $count = (int) $connection->fetchOne('SELECT COUNT(*) FROM job');

        $job = $this->dispatchJob();

        $this->assertSame($existing, $job);
        $this->assertSame($count, (int) $connection->fetchOne('SELECT COUNT(*) FROM job'));
    }

    public function testListenerUpdatesArgsAndStrategy(): void
    {
        $strategy = $this->strategy();
        $this->listen('job.dispatch.pre', function (Event $event) use ($strategy): void {
            $event->setParam('args', ['resource' => 'items', 'ids' => [1, 2]]);
            $event->setParam('strategy', $strategy);
        });

        $job = $this->dispatchJob();

        $this->assertSame(['resource' => 'items', 'ids' => [1, 2]], $job->getArgs());
        $this->assertSame([$job->getId()], $strategy->sent);
    }

    /**
     * @dataProvider jobWriterProvider
     */
    public function testListenerDisablesTheJobWriter(bool $jobWriter, bool $hasLog): void
    {
        $this->listen('job.send.pre', function (Event $event) use ($jobWriter): void {
            $event->setParam('job_writer', $jobWriter);
        });
        $logger = $this->getApplicationServiceLocator()->get('Omeka\Logger');
        $strategy = $this->strategy(function () use ($logger): void {
            $logger->notice('Message of the job {id}.', ['id' => 'x']); // @translate
        });

        $job = $this->dispatchJob($strategy);

        $this->assertSame($hasLog, strpos((string) $job->getLog(), 'Message of the job x.') !== false);
    }

    public function jobWriterProvider(): array
    {
        return [
            'with job writer' => [true, true],
            'without job writer' => [false, false],
        ];
    }

    protected function dispatcher(): Dispatcher
    {
        return $this->getApplicationServiceLocator()->get(\Omeka\Job\Dispatcher::class);
    }

    /**
     * Dispatch a job that does nothing.
     */
    protected function dispatchJob(?StrategyInterface $strategy = null): Job
    {
        $job = $this->dispatcher()->dispatch(
            \Omeka\Job\BatchDelete::class,
            ['resource' => 'items', 'ids' => []],
            $strategy ?: $this->strategy()
        );
        $this->jobIds[] = $job->getId();
        return $job;
    }

    protected function strategy(?callable $callback = null): StrategyInterface
    {
        return new class($callback) implements StrategyInterface {
            public $sent = [];

            protected $callback;

            public function __construct(?callable $callback)
            {
                $this->callback = $callback;
            }

            public function send(Job $job): void
            {
                $this->sent[] = $job->getId();
                if ($this->callback) {
                    ($this->callback)();
                }
            }
        };
    }

    protected function listen(string $event, callable $listener): void
    {
        $this->getApplicationServiceLocator()->get('SharedEventManager')
            ->attach(\Omeka\Job\Dispatcher::class, $event, $listener);
        $this->listeners[] = [$event, $listener];
    }
}
