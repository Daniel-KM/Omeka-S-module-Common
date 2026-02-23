<?php declare(strict_types=1);

namespace Common\File;

use Common\Mvc\Controller\Plugin\SpecifyMediaType;

class TempFile extends \Omeka\File\TempFile
{
    /**
     * @var \Common\Mvc\Controller\Plugin\SpecifyMediaType
     */
    protected $specifyMediaType;

    public function setSpecifyMediaType(SpecifyMediaType $specifyMediaType): \Omeka\File\TempFile
    {
        $this->specifyMediaType = $specifyMediaType;
        return $this;
    }

    public function getMediaType()
    {
        if (isset($this->mediaType)) {
            return $this->mediaType;
        }
        if (!file_exists($this->getTempPath())) {
            return null;
        }
        // Parent sets $this->mediaType, then refine it.
        $this->mediaType = parent::getMediaType();
        $this->mediaType = $this->specifyMediaType->__invoke($this->getTempPath(), $this->mediaType ?: null);
        return $this->mediaType;
    }
}
