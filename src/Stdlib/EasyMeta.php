<?php declare(strict_types=1);

namespace Common\Stdlib;

use Doctrine\DBAL\Connection;

class EasyMeta
{
    const RESOURCE_CLASSES = [
        'annotations' => \Annotate\Entity\Annotation::class,
        'assets' => \Omeka\Entity\Asset::class,
        'items' => \Omeka\Entity\Item::class,
        'item_sets' => \Omeka\Entity\ItemSet::class,
        'media' => \Omeka\Entity\Media::class,
        'resources' => \Omeka\Entity\Resource::class,
        'value_annotations' => \Omeka\Entity\ValueAnnotation::class,
    ];

    const RESOURCE_LABELS = [
        'annotations' => 'annotation', // @translate
        'assets' => 'asset', // @translate
        'items' => 'item', // @translate
        'item_sets' => 'item set', // @translate
        'media' => 'media', // @translate
        'resources' => 'resource', // @translate
        'properties' => 'property', // @translate
        'resource_classes' => 'resource class', // @translate
        'resource_templates' => 'resource template', // @translate
        'vocabularies' => 'vocabulary', // @translate
    ];

    const RESOURCE_LABELS_PLURAL = [
        'annotations' => 'annotations', // @translate
        'assets' => 'assets', // @translate
        'items' => 'items', // @translate
        'item_sets' => 'item sets', // @translate
        'media' => 'media', // @translate
        'resources' => 'resources', // @translate
        'properties' => 'properties', // @translate
        'resource_classes' => 'resource classes', // @translate
        'resource_templates' => 'resource templates', // @translate
        'vocabularies' => 'vocabularies', // @translate
    ];

    const RESOURCE_NAMES = [
        // Resource names.
        'annotations' => 'annotations',
        'assets' => 'assets',
        'items' => 'items',
        'item_sets' => 'item_sets',
        'media' => 'media',
        'resources' => 'resources',
        'value_annotations' => 'value_annotations',
        // Json-ld type.
        'oa:Annotation' => 'annotations',
        'o:Asset' => 'assets',
        'o:Item' => 'items',
        'o:ItemSet' => 'item_sets',
        'o:Media' => 'media',
        'o:Resource' => 'resources',
        'o:ValueAnnotation' => 'value_annotations',
        // Keys in json-ld representation.
        'oa:annotation' => 'annotations',
        'o:asset' => 'assets',
        'o:item' => 'items',
        'o:items' => 'items',
        'o:item_set' => 'item_sets',
        'o:site_item_set' => 'item_sets',
        'o:media' => 'media',
        '@annotations' => 'value_annotations',
        // Controllers and singular.
        'annotation' => 'annotations',
        'asset' => 'assets',
        'item' => 'items',
        'item-set' => 'item_sets',
        // 'media' => 'media',
        'resource' => 'resources',
        'value-annotation' => 'value_annotations',
        // Value data types.
        'resource:annotation' => 'annotations',
        // 'resource' => 'resources',
        'resource:item' => 'items',
        'resource:itemset' => 'item_sets',
        'resource:media' => 'media',
        // Representation class.
        \Annotate\Api\Representation\AnnotationRepresentation::class => 'annotations',
        \Omeka\Api\Representation\AssetRepresentation::class => 'assets',
        \Omeka\Api\Representation\ItemRepresentation::class => 'items',
        \Omeka\Api\Representation\ItemSetRepresentation::class => 'item_sets',
        \Omeka\Api\Representation\MediaRepresentation::class => 'media',
        \Omeka\Api\Representation\ResourceReference::class => 'resources',
        \Omeka\Api\Representation\ValueAnnotationRepresentation::class => 'value_annotations',
        // Entity class.
        \Annotate\Entity\Annotation::class => 'annotations',
        \Omeka\Entity\Asset::class => 'assets',
        \Omeka\Entity\Item::class => 'items',
        \Omeka\Entity\ItemSet::class => 'item_sets',
        \Omeka\Entity\Media::class => 'media',
        \Omeka\Entity\Resource::class => 'resources',
        \Omeka\Entity\ValueAnnotation::class => 'value_annotations',
        // Doctrine entity class (when using get_class() and not getResourceId().
        \DoctrineProxies\__CG__\Annotate\Entity\Annotation::class => 'annotations',
        \DoctrineProxies\__CG__\Omeka\Entity\Asset::class => 'assets',
        \DoctrineProxies\__CG__\Omeka\Entity\Item::class => 'items',
        \DoctrineProxies\__CG__\Omeka\Entity\ItemSet::class => 'item_sets',
        \DoctrineProxies\__CG__\Omeka\Entity\Media::class => 'media',
        // \DoctrineProxies\__CG__\Omeka\Entity\Resource::class => 'resources',
        \DoctrineProxies\__CG__\Omeka\Entity\ValueAnnotation::class => 'value_annotations',
        // Other deprecated, future or badly written names.
        'o:annotation' => 'annotations',
        'o:Annotation' => 'annotations',
        'o:annotations' => 'annotations',
        'o:assets' => 'assets',
        'resource:items' => 'items',
        'itemset' => 'item_sets',
        'item set' => 'item_sets',
        'item_set' => 'item_sets',
        'itemsets' => 'item_sets',
        'item sets' => 'item_sets',
        'item-sets' => 'item_sets',
        'o:itemset' => 'item_sets',
        'o:item-set' => 'item_sets',
        'o:itemsets' => 'item_sets',
        'o:item-sets' => 'item_sets',
        'o:item_sets' => 'item_sets',
        'resource:itemsets' => 'item_sets',
        'resource:item-set' => 'item_sets',
        'resource:item-sets' => 'item_sets',
        'resource:item_set' => 'item_sets',
        'resource:item_sets' => 'item_sets',
        'o:resource' => 'resources',
        'valueannotation' => 'value_annotations',
        'value annotation' => 'value_annotations',
        'value_annotation' => 'value_annotations',
        'valueannotations' => 'value_annotations',
        'value annotations' => 'value_annotations',
        'value-annotations' => 'value_annotations',
        'o:valueannotation' => 'value_annotations',
        'o:valueannotations' => 'value_annotations',
        'o:value-annotation' => 'value_annotations',
        'o:value-annotations' => 'value_annotations',
        'o:value_annotation' => 'value_annotations',
        'o:value_annotations' => 'value_annotations',
        'resource:valueannotation' => 'value_annotations',
        'resource:valueannotations' => 'value_annotations',
        'resource:value-annotation' => 'value_annotations',
        'resource:value-annotations' => 'value_annotations',
        'resource:value_annotation' => 'value_annotations',
        'resource:value_annotations' => 'value_annotations',
    ];

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var array
     */
    protected static $propertyIdsByTerms;

    /**
     * @var array
     */
    protected static $propertyIdsByTermsAndIds;

    /**
     * @var array
     */
    protected static $propertyLabelsByTerms;

    /**
     * @var array
     */
    protected static $propertyLabelsByTermsAndIds;

    /**
     * @var array
     */
    protected static $resourceClassIdsByTerms;

    /**
     * @var array
     */
    protected static $resourceClassIdsByTermsAndIds;

    /**
     * @var array
     */
    protected static $resourceClassLabelsByTerms;

    /**
     * @var array
     */
    protected static $resourceClassLabelsByTermsAndIds;

    /**
     * @var array
     */
    protected static $resourceTemplateIdsByLabels;

    /**
     * @var array
     */
    protected static $resourceTemplateIdsByLabelsAndIds;

    /**
     * @var array
     */
    protected static $resourceTemplateLabelsByLabelsAndIds;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Get the entity class from any class, type or name.
     *
     * @param string $name
     * @return string|null The entity class if any.
     */
    public function entityClass($name): ?string
    {
        return self::RESOURCE_CLASSES[self::RESOURCE_NAMES[$name] ?? null] ?? null;
    }

    /**
     * Get the resource api name from any class, type or name.
     *
     * @param string $name
     * @return string|null The resource name if any.
     */
    public function resourceName($name): ?string
    {
        return self::RESOURCE_NAMES[$name] ?? null;
    }

    /**
     * Get the resource label from any common resource name.
     *
     * @return string|null The singular label if any, not translated.
     */
    public function resourceLabel($name): ?string
    {
        return self::RESOURCE_LABELS[self::RESOURCE_NAMES[$name] ?? null] ?? null;
    }

    /**
     * Get the plural resource label from any common resource name.
     *
     * @return string|null The plural label if any, not translated.
     */
    public function resourceLabelPlural($name): ?string
    {
        return self::RESOURCE_LABELS_PLURAL[self::RESOURCE_NAMES[$name] ?? null] ?? null;
    }

    /**
     * Get a property id by JSON-LD term or by numeric id.
     *
     * @param int|string|null $termOrId A id or a term.
     * @return int|null The property id matching term or id.
     */
    public function propertyId($termOrId): ?int
    {
        if (is_null(static::$propertyIdsByTermsAndIds)) {
            $this->initProperties();
        }
        return static::$propertyIdsByTermsAndIds[$termOrId] ?? null;
    }

    /**
     * Get property ids by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return int[] The property ids matching terms or ids, or all properties
     * by terms. When the input contains terms and ids matching the same
     * properties, they are all returned.
     */
    public function propertyIds($termsOrIds = null): array
    {
        if (is_null(static::$propertyIdsByTermsAndIds)) {
            $this->initProperties();
        }
        if (is_null($termsOrIds)) {
            return static::$propertyIdsByTerms;
        }
        if (is_scalar($termsOrIds)) {
            $termsOrIds = [$termsOrIds];
        }
        return array_intersect_key(static::$propertyIdsByTermsAndIds, array_flip($termsOrIds));
    }

    /**
     * Get a property term by JSON-LD term or by numeric id.
     *
     * @param int|string|null $termOrId A id or a term.
     * @return string|null The property term matching term or id.
     */
    public function propertyTerm($termOrId): ?string
    {
        if (is_null(static::$propertyIdsByTermsAndIds)) {
            $this->initProperties();
        }
        if (!isset(static::$propertyIdsByTermsAndIds[$termOrId])) {
            return null;
        }
        return is_numeric($termOrId)
            ? array_search($termOrId, static::$propertyIdsByTerms)
            : $termOrId;
    }

    /**
     * Get property terms by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[] The property terms matching terms or ids, or all
     * properties by ids. When the input contains terms and ids matching the
     * same properties, they are all returned.
     */
    public function propertyTerms($termsOrIds = null): array
    {
        if (is_null(static::$propertyIdsByTermsAndIds)) {
            $this->initProperties();
        }
        if (is_null($termsOrIds)) {
            return array_flip(static::$propertyIdsByTerms);
        }
        if (is_scalar($termsOrIds)) {
            $termsOrIds = [$termsOrIds];
        }
        // TODO Keep original order.
        return array_intersect_key(static::$propertyIdsByTermsAndIds, array_flip($termsOrIds));
    }

    /**
     * Get a property label by JSON-LD term or by numeric id.
     *
     * @param int|string|null $termOrId A id or a term.
     * @return string|null The property label matching term or id. The label is
     * not translated.
     */
    public function propertyLabel($termOrId): ?string
    {
        if (is_null(static::$propertyIdsByTermsAndIds)) {
            $this->initProperties();
        }
        return static::$propertyLabelsByTermsAndIds[$termOrId] ?? null;
    }

    /**
     * Get property labels by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[] The property labels matching terms or ids, or all
     * properties labels by terms. When the input contains terms and ids
     * matching the same properties, they are all returned. Labels are not
     * translated.
     */
    public function propertyLabels($termsOrIds = null): array
    {
        if (is_null($termsOrIds)) {
            if (is_null(static::$propertyLabelsByTerms)) {
                $this->initProperties();
            }
            return static::$propertyLabelsByTerms;
        }
        if (is_scalar($termsOrIds)) {
            $termsOrIds = [$termsOrIds];
        }
        return array_intersect_key(static::$propertyLabelsByTermsAndIds, array_flip($termsOrIds));
    }

    /**
     * Get a resource class id by JSON-LD term or by numeric id.
     *
     * @param int|string|null $termOrId A id or a term.
     * @return int|null The resource class id matching term or id.
     */
    public function resourceClassId($termOrId): ?int
    {
        if (is_null(static::$resourceClassIdsByTermsAndIds)) {
            $this->initResourceClasses();
        }
        return static::$resourceClassIdsByTermsAndIds[$termOrId] ?? null;
    }

    /**
     * Get resource class ids by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return int[] The resource class ids matching terms or ids, or all
     * resource classes by terms. When the input contains terms and ids matching
     * the same resource classes, they are all returned.
     */
    public function resourceClassIds($termsOrIds = null): array
    {
        if (is_null(static::$resourceClassIdsByTermsAndIds)) {
            $this->initResourceClasses();
        }
        if (is_null($termsOrIds)) {
            return static::$resourceClassIdsByTerms;
        }
        if (is_scalar($termsOrIds)) {
            $termsOrIds = [$termsOrIds];
        }
        return array_intersect_key(static::$resourceClassIdsByTermsAndIds, array_flip($termsOrIds));
    }

    /**
     * Get a resource class term by JSON-LD term or by numeric id.
     *
     * @param int|string|null $termOrId A id or a term.
     * @return string|null The resource class term matching term or id.
     */
    public function resourceClassTerm($termOrId): ?string
    {
        if (is_null(static::$resourceClassIdsByTermsAndIds)) {
            $this->initResourceClasses();
        }
        if (!isset(static::$resourceClassIdsByTermsAndIds[$termOrId])) {
            return null;
        }
        return is_numeric($termOrId)
            ? array_search($termOrId, static::$resourceClassIdsByTerms)
            : $termOrId;
    }

    /**
     * Get resource class terms by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[] The resource class terms matching terms or ids, or all
     * resource classes by ids.
     */
    public function resourceClassTerms($termsOrIds = null): array
    {
        if (is_null(static::$resourceClassIdsByTermsAndIds)) {
            $this->initResourceClasses();
        }
        if (is_null($termsOrIds)) {
            return array_flip(static::$resourceClassIdsByTerms);
        }
        if (is_scalar($termsOrIds)) {
            $termsOrIds = [$termsOrIds];
        }
        // TODO Keep original order.
        return array_intersect_key(static::$resourceClassIdsByTermsAndIds, array_flip($termsOrIds));
    }

    /**
     * Get a resource class label by JSON-LD term or by numeric id.
     *
     * @param int|string|null $termOrId A id or a term.
     * @return string|null The resource class label matching term or id. The
     * label is not translated.
     */
    public function resourceClassLabel($termOrId): ?string
    {
        if (is_null(static::$resourceClassIdsByTermsAndIds)) {
            $this->initResourceClasses();
        }
        return static::$resourceClassLabelsByTermsAndIds[$termOrId] ?? null;
    }

    /**
     * Get resource class labels by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[] The resource class labels matching terms or ids, or all
     * resource class labels by terms. When the input contains terms and ids
     * matching the same resource classes, they are all returned. Labels are not
     * translated.
     */
    public function resourceClassLabels($termsOrIds = null): array
    {
        if (is_null($termsOrIds)) {
            if (is_null(static::$resourceClassLabelsByTerms)) {
                $this->initResourceClasses();
            }
            return static::$resourceClassLabelsByTerms;
        }
        if (is_scalar($termsOrIds)) {
            $termsOrIds = [$termsOrIds];
        }
        $terms = $this->resourceClassTerms($termsOrIds);
        return array_intersect_key(static::$resourceClassLabelsByTermsAndIds, array_flip($terms));
    }

    /**
     * Get a resource template id by label or by numeric id.
     *
     * @param int|string|null $labelOrId A id or a label.
     * @return int|null The resource template id matching label or id.
     */
    public function resourceTemplateId($labelOrId): ?int
    {
        if (is_null(static::$resourceTemplateIdsByLabelsAndIds)) {
            $this->initResourceTemplates();
        }
        return static::$resourceTemplateIdsByLabelsAndIds[$labelOrId] ?? null;
    }

    /**
     * Get resource template ids by labels or by numeric ids.
     *
     * @param array|int|string|null $labelsOrIds One or multiple ids or labels.
     * @return string[] The resource template ids matching labels or ids, or all
     * resource templates by labels. When the input contains labels and ids
     * matching the same templates, they are all returned.
     */
    public function resourceTemplateIds($labelsOrIds = null): array
    {
        if (is_null(static::$resourceTemplateIdsByLabelsAndIds)) {
            $this->initResourceTemplates();
        }
        if (is_null($labelsOrIds)) {
            return static::$resourceTemplateByLabels;
        }
        if (is_scalar($labelsOrIds)) {
            $labelsOrIds = [$labelsOrIds];
        }
        return array_intersect_key(static::$resourceTemplateIdsByLabelsAndIds, array_flip($labelsOrIds));
    }

    /**
     * Get a resource template label by label or by numeric id.
     *
     * @param int|string|null $labelOrId A id or a label.
     * @return string|null The resource template label matching label or id.
     */
    public function resourceTemplateLabel($labelOrId): ?string
    {
        if (is_null(static::$resourceTemplateIdsByLabelsAndIds)) {
            $this->initResourceTemplates();
        }
        return static::$resourceTemplateIdsByLabelsAndIds[$labelOrId] ?? null;
    }

    /**
     * Get one or more resource template labels by labels or by numeric ids.
     *
     * @param array|int|string|null $labelsOrIds One or multiple ids or labels.
     * @return string[] The resource template labels matching labels or ids, or
     * all resource templates labels. When the input contains labels and ids
     * matching the same templates, they are all returned.
     */
    public function resourceTemplateLabels($labelsOrIds = null): array
    {
        if (is_null(static::$resourceTemplateIdsByLabelsAndIds)) {
            $this->initResourceTemplates();
        }
        if (is_null($labelsOrIds)) {
            return array_flip(static::$resourceTemplateIdsByLabels);
        }
        if (is_scalar($labelsOrIds)) {
            $labelsOrIds = [$labelsOrIds];
        }
        // TODO Keep original order.
        return array_intersect_key(static::$resourceTemplateLabelsByLabelsAndIds, array_flip($labelsOrIds));
    }

    protected function initProperties(): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select(
                'CONCAT(`vocabulary`.`prefix`, ":", `property`.`local_name`) AS term',
                '`property`.`id` AS id',
                '`property`.`label` AS label'
            )
            ->from('`property`', 'property')
            ->innerJoin('property', 'vocabulary', 'vocabulary', '`property`.`vocabulary_id` = `vocabulary`.`id`')
            ->groupBy('`property`.`id`')
            ->orderBy('`vocabulary`.`id`', 'asc')
            ->addOrderBy('`property`.`id`', 'asc')
        ;
        $result = $this->connection->executeQuery($qb)->fetchAllAssociative();

        static::$propertyIdsByTerms = array_map('intval', array_column($result, 'id', 'term'));
        static::$propertyIdsByTermsAndIds = static::$propertyIdsByTerms
            + array_column($result, 'id', 'id');
        static::$propertyLabelsByTerms = array_column($result, 'label', 'term');
        static::$propertyLabelsByTermsAndIds = static::$propertyLabelsByTerms
            + array_column($result, 'label', 'id');
    }

    protected function initResourceClasses(): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select(
                'CONCAT(`vocabulary`.`prefix`, ":", `resource_class`.`local_name`) AS term',
                '`resource_class`.`id` AS id',
                '`resource_class`.`label` AS label'
            )
            ->from('`resource_class`', 'resource_class')
            ->innerJoin('resource_class', 'vocabulary', 'vocabulary', '`resource_class`.`vocabulary_id` = `vocabulary`.`id`')
            ->groupBy('`resource_class`.`id`')
            ->orderBy('`vocabulary`.`id`', 'asc')
            ->addOrderBy('`resource_class`.`id`', 'asc')
        ;
        $result = $this->connection->executeQuery($qb)->fetchAllAssociative();
        static::$resourceClassIdsByTerms = array_map('intval', array_column($result, 'id', 'term'));
        static::$resourceClassIdsByTermsAndIds = static::$resourceClassIdsByTerms
            + array_column($result, 'id', 'id');
        static::$resourceClassLabelsByTerms = array_column($result, 'label', 'term');
        static::$resourceClassLabelsByTermsAndIds = static::$resourceClassLabelsByTerms
            + array_column($result, 'label', 'id');
    }

    protected function initResourceTemplates(): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select(
                '`resource_template`.`label` AS label',
                '`resource_template`.`id` AS id'
            )
            ->from('resource_template', 'resource_template')
            ->groupBy('`resource_template`.`id`')
            ->orderBy('`resource_template`.`label`', 'asc')
        ;
        $result = $this->connection->executeQuery($qb)->fetchAllKeyValue();
        static::$resourceTemplateIdsByLabels = array_map('intval', $result);
        static::$resourceTemplateIdsByLabelsAndIds = static::$resourceTemplateIdsByLabels
            + array_column($result, 'id', 'id');
        static::$resourceTemplateLabelsByLabelsAndIds = array_combine(array_keys(static::$resourceTemplateIdsByLabels), array_keys(static::$resourceTemplateIdsByLabels))
            + array_flip(static::$resourceTemplateIdsByLabels);
    }
}
