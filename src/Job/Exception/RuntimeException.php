<?php declare(strict_types=1);

namespace Common\Job\Exception;

/**
 * Failure of a whole job, with a message logged like a PSR-3 one.
 *
 * The message may contain placeholders like "{id}", replaced by the context
 * when it is logged, so it remains translatable.
 */
class RuntimeException extends \Omeka\Job\Exception\RuntimeException
{
    /**
     * @var array
     */
    protected $context = [];

    public function __construct(string $message = '', array $context = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
