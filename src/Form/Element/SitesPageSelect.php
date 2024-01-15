<?php declare(strict_types=1);

namespace Common\Form\Element;

use Omeka\Api\Representation\SitePageRepresentation;

/**
 * Different from core SitePageSelect.
 *
 * @see \Omeka\Form\Element\SitePageSelect
 */
class SitesPageSelect extends AbstractGroupBySiteSelect
{
    public function getResourceName(): string
    {
        return 'site_pages';
    }

    public function getValueLabel(SitePageRepresentation $resource): string
    {
        return $resource->title();
    }
}
