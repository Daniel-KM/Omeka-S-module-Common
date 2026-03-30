<?php declare(strict_types=1);

namespace Common\Stdlib;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Job\Dispatcher;

/**
 * Accumulate job dispatches during a single request and dispatch them once
 * at shutdown.
 *
 * This avoids dispatching N identical jobs when N resources are saved in a
 * single request (batch create, batch update, import). Doctrine 3 does not
 * support flush($entity), so dispatching inside an API event listener would
 * trigger a global flush that conflicts with pending entities in the
 * UnitOfWork.
 *
 * Usage in a Module event listener:
 *
 *     $deferred = $services
 *         ->get('Common\DeferredJobDispatch');
 *     $deferred->defer(
 *         \MyModule\Job\MyJob::class,
 *         'my_key',
 *         ['item_ids' => $itemId]
 *     );
 *
 * At shutdown, one job per unique $key is dispatched, with all accumulated
 * params merged via the optional $argBuilder callback.
 */
class DeferredJobDispatch
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var array<string, array{class: string, params: list<mixed>,
     *     argBuilder: ?callable}>
     */
    protected $pending = [];

    /**
     * @var bool
     */
    protected $shutdownRegistered = false;

    public function __construct(ServiceLocatorInterface $services)
    {
        $this->services = $services;
    }

    /**
     * Accumulate a job dispatch for later.
     *
     * @param string $jobClass Fully qualified job class.
     * @param string $key Unique key to group dispatches (one job per key at
     *   shutdown).
     * @param mixed $params Data to accumulate.
     * @param callable|null $argBuilder Optional callback to merge all params
     *   into job args: fn(string $key, array $allParams): array. Default:
     *   collect scalar values per key into space-separated strings.
     */
    public function defer(
        string $jobClass,
        string $key,
        $params = null,
        ?callable $argBuilder = null
    ): void {
        if (!isset($this->pending[$key])) {
            $this->pending[$key] = [
                'class' => $jobClass,
                'params' => [],
                'argBuilder' => $argBuilder,
            ];
        }
        if ($params !== null) {
            $this->pending[$key]['params'][] = $params;
        }

        if (!$this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            register_shutdown_function(
                [$this, 'dispatchAll']
            );
        }
    }

    /**
     * Dispatch all accumulated jobs. Called at shutdown.
     */
    public function dispatchAll(): void
    {
        if (empty($this->pending)) {
            return;
        }

        $jobs = $this->pending;
        $this->pending = [];
        $this->shutdownRegistered = false;

        try {
            $dispatcher = $this->services
                ->get(Dispatcher::class);
        } catch (\Throwable $e) {
            return;
        }

        foreach ($jobs as $key => $data) {
            $builder = $data['argBuilder']
                ?: [$this, 'defaultArgBuilder'];
            $result = $builder($key, $data['params']);

            // If the builder returns a list of arrays (numeric keys),
            // dispatch one job per entry. Otherwise, dispatch a single job.
            $dispatches = isset($result[0])
                && is_array($result[0])
                    ? $result
                    : [$result];

            foreach ($dispatches as $args) {
                try {
                    $dispatcher->dispatch(
                        $data['class'], $args
                    );
                } catch (\Throwable $e) {
                    // Silently fail during shutdown.
                }
            }
        }
    }

    /**
     * Default arg builder: collect scalar values per key into
     * space-separated strings.
     */
    public function defaultArgBuilder(
        string $key,
        array $allParams
    ): array {
        if (empty($allParams)) {
            return [];
        }

        $merged = [];
        foreach ($allParams as $params) {
            if (is_array($params)) {
                foreach ($params as $k => $v) {
                    if (is_int($v) || is_string($v)) {
                        $merged[$k][] = $v;
                    } else {
                        $merged[$k] = $v;
                    }
                }
            } elseif (is_scalar($params)) {
                $merged['ids'][] = $params;
            }
        }

        foreach ($merged as $k => $v) {
            if (is_array($v)) {
                $merged[$k] = implode(
                    ' ', array_unique(
                        array_map('strval', $v)
                    )
                );
            }
        }

        return $merged;
    }
}
