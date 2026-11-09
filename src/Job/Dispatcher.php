<?php declare(strict_types=1);

namespace Common\Job;

use DateTime;
use Doctrine\ORM\EntityManager;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerAwareTrait;
use Omeka\Entity\Job;
use Omeka\Job\DispatchStrategy\StrategyInterface;
use Omeka\Job\Exception;
use Omeka\Log\Writer\Job as JobWriter;

/**
 * Job dispatcher with extension points, so the modules do not replace it.
 *
 * Events, with the identifier "Omeka\Job\Dispatcher":
 * - "job.dispatch.pre", params "class", "args" and "strategy": a listener may
 *   update the args or the strategy, or return an existing job to use instead
 *   of a new one.
 * - "job.send.pre", params "job", "strategy" and "job_writer": a listener may
 *   add log processors or disable the job writer.
 *
 * The job is created via dbal to avoid a global flush that could conflict with
 * pending entities in the unit of work, since doctrine 3 cannot flush a single
 * entity.
 */
class Dispatcher extends \Omeka\Job\Dispatcher implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    protected $eventIdentifier = [
        \Omeka\Job\Dispatcher::class,
    ];

    public function dispatch($class, $args = null, ?StrategyInterface $strategy = null)
    {
        if (!class_exists($class)) {
            throw new Exception\InvalidArgumentException(sprintf('The job class "%s" does not exist.', $class));
        }
        if (!is_subclass_of($class, \Omeka\Job\JobInterface::class)) {
            throw new Exception\InvalidArgumentException(sprintf('The job class "%s" does not implement Omeka\Job\JobInterface.', $class));
        }

        $events = $this->getEventManager();
        $params = $events->prepareArgs([
            'class' => $class,
            'args' => $args,
            'strategy' => $strategy,
        ]);
        $responses = $events->triggerUntil(
            fn ($response) => $response instanceof Job,
            'job.dispatch.pre',
            $this,
            $params
        );
        if ($responses->stopped() && $responses->last() instanceof Job) {
            return $responses->last();
        }
        $args = $params['args'];
        $strategy = $params['strategy'];

        // Use a new entity manager only if closed.
        if (!$this->entityManager->isOpen()) {
            $this->entityManager = $this->getNewEntityManager($this->entityManager);
        }

        // Use the id of the owner only, to avoid a doctrine issue when a job
        // dispatches another job.
        $owner = $this->auth->getIdentity();

        $connection = $this->entityManager->getConnection();
        $connection->insert('job', [
            'status' => Job::STATUS_STARTING,
            'class' => $class,
            'args' => $args ? json_encode($args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'owner_id' => $owner ? $owner->getId() : null,
            'started' => (new DateTime('now'))->format('Y-m-d H:i:s'),
        ]);
        $jobId = (int) $connection->lastInsertId();

        // Load the entity for the strategy and the return value.
        $job = $this->entityManager->find(Job::class, $jobId);

        $this->send($job, $strategy ?: $this->getDispatchStrategy());
        return $job;
    }

    public function send(Job $job, StrategyInterface $strategy): void
    {
        $params = $this->getEventManager()->prepareArgs([
            'job' => $job,
            'strategy' => $strategy,
            'job_writer' => true,
        ]);
        $this->getEventManager()->trigger('job.send.pre', $this, $params);

        if ($params['job_writer']) {
            $writer = new JobWriter($job);
            $writer->setFormatter(new \Common\Log\Formatter\PsrLogSimple());
            $this->logger->addWriter($writer);
        }

        try {
            $strategy->send($job);
        } catch (\Throwable $e) {
            $this->logger->err((string) $e);
            // Account for "inside Doctrine" errors that close the entity
            // manager.
            $entityManager = $this->entityManager->isOpen()
                ? $this->entityManager
                : $this->getNewEntityManager($this->entityManager);
            // Reload the job, that may have been updated during the process,
            // but keep the logs, since the job object itself is up-to-date.
            $entityManager->clear();
            try {
                $jobEntity = $entityManager->find(Job::class, $job->getId());
            } catch (\Throwable $e) {
                return;
            }
            $jobEntity->setLog($job->getLog());
            $jobEntity->setStatus(Job::STATUS_ERROR);
            $jobEntity->setEnded(new DateTime('now'));
            $entityManager->persist($jobEntity);
            $entityManager->flush();
        }
    }

    protected function getNewEntityManager(EntityManager $entityManager): EntityManager
    {
        return EntityManager::create(
            $entityManager->getConnection(),
            $entityManager->getConfiguration(),
            $entityManager->getEventManager()
        );
    }
}
