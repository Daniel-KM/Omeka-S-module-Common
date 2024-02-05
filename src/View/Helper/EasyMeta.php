<?php declare(strict_types=1);

namespace Common\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class EasyMeta extends AbstractHelper
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
