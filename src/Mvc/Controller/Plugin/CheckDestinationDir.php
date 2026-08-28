<?php declare(strict_types=1);

namespace Common\Mvc\Controller\Plugin;

use Common\Stdlib\DirectoryManager;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class CheckDestinationDir extends AbstractPlugin
{
    /**
     * @var DirectoryManager
     */
    protected $directoryManager;

    public function __construct(DirectoryManager $directoryManager)
    {
        $this->directoryManager = $directoryManager;
    }

    /**
     * Check or create the destination folder, optionally denying web access.
     *
     * @see \Common\Stdlib\DirectoryManager::checkDestinationDir()
     */
    public function __invoke(string $dirPath, bool $protect = false): ?string
    {
        return $this->directoryManager->checkDestinationDir($dirPath, $protect);
    }
}
