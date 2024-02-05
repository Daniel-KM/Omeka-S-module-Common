<?php declare(strict_types=1);

namespace Common\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class EasyMeta extends AbstractPlugin
{
    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    public function __construct(\Common\Stdlib\EasyMeta $easyMeta)
    {
        $this->easyMeta = $easyMeta;
    }

    public function __invoke(): \Common\Stdlib\EasyMeta
    {
        return $this->easyMeta;
    }

    public function __call(string $name , array $arguments)
    {
        return $this->easyMeta->$name(...$arguments);
    }
}
