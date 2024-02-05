<?php declare(strict_types=1);

namespace Common\Stdlib;

use Doctrine\DBAL\Connection;
use Omeka\DataType\Manager as DataTypeManager;

class EasyMeta
{
    const DATA_TYPES_MAIN = [
        'literal' => 'literal',
        'uri' => 'uri',
        'resource' => 'resource',
        'resource:item' => 'resource',
        'resource:itemset' => 'resource',
        'resource:media' => 'resource',
        // Module Annotate (for future usage).
        'resource:annotation' => 'resource',
        'annotation' => 'resource',
        // Module DataTypeGeometry.
        'geography' => 'literal',
        'geography:coordinates' => 'literal',
        'geometry' => 'literal',
        'geometry:coordinates' => 'literal',
        'geometry:position' => 'literal',
        // Module DataTypeGeometry (deprecated).
        'geometry:geography' => 'literal',
        'geometry:geography:coordinates' => 'literal',
        'geometry:geometry' => 'literal',
        'geometry:geometry:coordinates' => 'literal',
        'geometry:geometry:position' => 'literal',
        // Module DataTypePlace.
        'place' => 'uri',
        // Module DataTypeRdf.
        'html' => 'literal',
        'xml' => 'literal',
        'boolean' => 'literal',
        // Module NumericDataTypes.
        'numeric:timestamp' => 'literal',
        'numeric:integer' => 'literal',
        'numeric:duration' => 'literal',
        'numeric:interval' => 'literal',
        // Specific module.
        'email' => 'literal',
    ];

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
     * @var \Omeka\DataType\Manager
     */
    protected $dataTypeManager;

    /**
     * @var array
     */
    protected static $dataTypesByNames;

    /**
     * @var array
     */
    protected static $dataTypesMainCustomVocabs;

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

    /**
     * @var array
     */
    protected static $vocabularyIdsByPrefixes;

    /**
     * @var array
     */
    protected static $vocabularyIdsByUris;

    /**
     * @var array
     */
    protected static $vocabularyIdsByPrefixesAndUrisAndIds;

    /**
     * @var array
     */
    protected static $vocabularyPrefixesByPrefixesAndUrisAndIds;

    /**
     * @var array
     */
    protected static $vocabularyUrisByPrefixesAndUrisAndIds;

    public function __construct(
        Connection $connection,
        DataTypeManager $dataTypeManager
    ) {
        $this->connection = $connection;
        $this->dataTypeManager = $dataTypeManager;
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
        return static::RESOURCE_CLASSES[static::RESOURCE_NAMES[$name] ?? null] ?? null;
    }

    /**
     * Get the resource api name from any class, type or name.
     *
     * @param string $name
     * @return string|null The resource name if any.
     */
    public function resourceName($name): ?string
    {
        return static::RESOURCE_NAMES[$name] ?? null;
    }

    /**
     * Get the resource label from any common resource name.
     *
     * @return string|null The singular label if any, not translated.
     */
    public function resourceLabel($name): ?string
    {
        return static::RESOURCE_LABELS[static::RESOURCE_NAMES[$name] ?? null] ?? null;
    }

    /**
     * Get the plural resource label from any common resource name.
     *
     * @return string|null The plural label if any, not translated.
     */
    public function resourceLabelPlural($name): ?string
    {
        return static::RESOURCE_LABELS_PLURAL[static::RESOURCE_NAMES[$name] ?? null] ?? null;
    }

    /**
     * Get a data type name by name.
     *
     * @param string|null $name A name.
     * @return string|null The data type name matching the name.
     */
    public function dataTypeName(?string $dataType): ?string
    {
        if (!$dataType) {
            return null;
        }
        if (is_null(static::$dataTypesByNames)) {
            $this->initDataTypes();
        }
        $dataType = mb_strtolower($dataType);
        return static::$dataTypesByNames[$dataType] ?? null;
    }

    /**
     * Get data type names by names or all data types.
     *
     * @param array|int|string|null $dataTypes One or multiple data types.
     * @return string[] The matching data type names or all data types, by data
     * types.
     */
    public function dataTypeNames($dataTypes = null): array
    {
        if (is_null(static::$dataTypesByNames)) {
            $this->initDataTypes();
        }
        if (is_null($dataTypes)) {
            return static::$dataTypesByNames;
        }
        if (is_scalar($dataTypes)) {
            $dataTypes = [$dataTypes];
        }
        return array_intersect_key(static::$dataTypesByNames, array_flip($dataTypes));
    }

    /**
     * Get the main data type ("literal", "resource", or "uri") of a name.
     *
     * @param string|null $name A name.
     * @return string|null The main data type name matching the name.
     */
    public function dataTypeMain(?string $dataType): ?string
    {
        if (!$dataType) {
            return null;
        }
        $name = mb_strtolower($dataType);
        if (isset(static::DATA_TYPES_MAIN[$dataType])) {
            return static::DATA_TYPES_MAIN[$dataType];
        }
        // Manage an exception in ValueSuggest: geonames features has no uri.
        if ($name === 'valuesuggestall:geonames:features') {
            return 'literal';
        }
        // Module ValueSuggest.
        if (substr($dataType, 0, 13) === 'valuesuggest:'
            || substr($dataType, 0, 16) === 'valuesuggestall:'
        ) {
            return 'uri';
        }
        if (substr($dataType, 0, 12) === 'customvocab:') {
            return $this->dataTypeMainCustomVocab($dataType);
        }
        return null;
    }

    /**
     * Get the main type of the custom vocab: "literal", "resource" or "uri".
     *
     * @todo Check for dynamic custom vocabs.
     */
    public function dataTypeMainCustomVocab(?string $dataType): ?string
    {
        if (!$dataType) {
            return null;
        }
        if (is_null(static::$dataTypesMainCustomVocabs)) {
            $this->initDataTypesMainCustomVocabs();
        }
        $dataType = mb_strtolower($dataType);
        return static::$dataTypesMainCustomVocabs[$dataType] ?? null;
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

    /**
     * Get a vocabulary id by uri, prefix or by numeric id.
     *
     * @param int|string|null $prefixOrUriOrId A id, uri or prefix.
     * @return int|null The vocabulary id matching uri, prefix or id.
     */
    public function vocabularyId($prefixOrUriOrId): ?int
    {
        if (is_null(static::$vocabularyIdsByPrefixesAndUrisAndIds)) {
            $this->initVocabularies();
        }
        return static::$vocabularyIdsByPrefixesAndUrisAndIds[$prefixOrUriOrId] ?? null;
    }

    /**
     * Get vocabulary ids by uris, prefix or by numeric ids.
     *
     * @param array|int|string|null $prefixesOrUrisOrIds One or multiple ids,
     *   uris or prefixes.
     * @return int[] The vocabulary ids matching uris, prefixes or ids, or all
     * vocabularies by prefixes. When the input contains uris, prefixes and ids
     * matching the same vocabularies, they are all returned.
     */
    public function vocabularyIds($prefixesOrUrisOrIds = null): array
    {
        if (is_null(static::$vocabularyIdsByPrefixesAndUrisAndIds)) {
            $this->initVocabularies();
        }
        if (is_null($prefixesOrUrisOrIds)) {
            return static::$vocabularyIdsByPrefixes;
        }
        if (is_scalar($prefixesOrUrisOrIds)) {
            $prefixesOrUrisOrIds = [$prefixesOrUrisOrIds];
        }
        return array_intersect_key(static::$vocabularyIdsByPrefixesAndUrisAndIds, array_flip($prefixesOrUrisOrIds));
    }

    /**
     * Get a vocabulary prefix by uri, prefix or by numeric id.
     *
     * @param int|string|null $prefixOrUriOrId A id, uri or prefix.
     * @return int|null The vocabulary prefix matching uri, prefix or id.
     */
    public function vocabularyPrefix($prefixOrUriOrId): ?string
    {
        if (is_null(static::$vocabularyIdsByPrefixesAndUrisAndIds)) {
            $this->initVocabularies();
        }
        if (!isset(static::$vocabularyIdsByPrefixesAndUrisAndIds[$prefixOrUriOrId])) {
            return null;
        }
        if (is_numeric($prefixOrUriOrId)) {
            return array_search($prefixOrUriOrId, static::$vocabularyIdsByPrefixes);
        }
        $id = static::$vocabularyIdsByPrefixesAndUrisAndIds[$prefixOrUriOrId];
        return array_search($id, static::$vocabularyIdsByPrefixes);
    }

    /**
     * Get vocabulary prefixes by uris, prefixes or numeric ids.
     *
     * @param array|int|string|null $prefixesOrUrisOrIds One or multiple ids,
     *   uris or prefixes.
     * @return string[] The vocabulary prefixes matching uris, prefixes or ids,
     * or all vocabularies prefixes by ids. When the input contains uris,
     * prefixes and ids matching the same vocabularies, they are all returned.
     */
    public function vocabularyPrefixes($prefixesOrUrisOrIds = null): array
    {
        if (is_null(static::$vocabularyIdsByPrefixesAndUrisAndIds)) {
            $this->initVocabularies();
        }
        if (is_null($prefixesOrUrisOrIds)) {
            return array_flip(static::$vocabularyIdsByPrefixes);
        }
        if (is_scalar($prefixesOrUrisOrIds)) {
            $prefixesOrUrisOrIds = [$prefixesOrUrisOrIds];
        }
        // TODO Keep original order.
        return array_intersect_key(static::$vocabularyPrefixesByPrefixesAndUrisAndIds, array_flip($prefixesOrUrisOrIds));
    }

    /**
     * Get a vocabulary uri by uri, prefix or numeric id.
     *
     * @param int|string|null $prefixOrUriOrId A id, uri or prefix.
     * @return string|null The vocabulary uri matching uri, prefix or id.
     */
    public function vocabularyUri($prefixOrUriOrId): ?string
    {
        if (is_null(static::$vocabularyIdsByPrefixesAndUrisAndIds)) {
            $this->initVocabularies();
        }
        if (!isset(static::$vocabularyIdsByPrefixesAndUrisAndIds[$prefixOrUriOrId])) {
            return null;
        }
        if (is_numeric($prefixOrUriOrId)) {
            return array_search($prefixOrUriOrId, static::$vocabularyIdsByUris);
        }
        $id = static::$vocabularyIdsByPrefixesAndUrisAndIds[$prefixOrUriOrId];
        return array_search($id, static::$vocabularyIdsByUris);
    }

    /**
     * Get vocabulary uris by uris, prefixes or numeric ids.
     *
     * @param array|int|string|null $prefixesOrUrisOrIds One or multiple ids,
     *   uris or prefixes.
     * @return string[] The vocabulary uris matching terms or ids, or all
     * vocabulary uris by prefixes. When the input contains uris, prefixes and
     * ids matching the same vocabularies, they are all returned.
     */
    public function vocabularyUrisByPrefixes($prefixesOrUrisOrIds = null): array
    {
        if (is_null(static::$vocabularyIdsByPrefixesAndUrisAndIds)) {
            $this->initVocabularies();
        }
        if (is_null($prefixesOrUrisOrIds)) {
            return array_combine(array_keys(static::$vocabularyIdsByPrefixes), array_keys(static::$vocabularyIdsByUris));
        }
        if (is_scalar($prefixesOrUrisOrIds)) {
            $prefixesOrUrisOrIds = [$prefixesOrUrisOrIds];
        }
        // TODO Keep original order.
        return array_intersect_key(static::$vocabularyUrisByPrefixesAndUrisAndIds, array_flip($prefixesOrUrisOrIds));
    }

    protected function initDataTypes(): void
    {
        static::$dataTypesByNames = $this->dataTypeManager->getRegisteredNames();
        static::$dataTypesByNames = array_combine(static::$dataTypesByNames, static::$dataTypesByNames);
    }

    protected function initDataTypesMainCustomVocabs(): void
    {
        $hasCustomVocab = class_exists('CustomVocab\Module');
        if ($hasCustomVocab) {
            /*
            $sql = <<<'SQL'
            SELECT
                GROUP_CONCAT(DISTINCT CASE WHEN `terms` != "" THEN `id` ELSE NULL END ORDER BY `id` ASC SEPARATOR " ") AS 'literal',
                GROUP_CONCAT(DISTINCT CASE WHEN `item_set_id` IS NOT NULL THEN `id` ELSE NULL END ORDER BY `id` ASC SEPARATOR " ") AS 'resource',
                GROUP_CONCAT(DISTINCT CASE WHEN `uris` != "" THEN `id` ELSE NULL END ORDER BY `id` ASC SEPARATOR " ") AS 'uri'
            FROM `custom_vocab`;
            SQL;
            $customVocabsByType = $site->get('Omeka\Connection')->executeQuery($sql)->fetchAssociative() ?: ['literal' => '', 'resource' => '', 'uri' => ''];
             */
            $sql = <<<'SQL'
SELECT
    `id` AS id,
    CASE
        WHEN `uris` != "" THEN "uri"
        WHEN `item_set_id` IS NOT NULL THEN "resource"
        ELSE "literal"
    END AS "type"
FROM `custom_vocab`
ORDER BY `id` ASC;
SQL;
            static::$dataTypesMainCustomVocabs = $this->connection->executeQuery($sql)->fetchAllKeyValue() ?: [];
        } else {
            static::$dataTypesMainCustomVocabs = [];
        }
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

    protected function initVocabularies(): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select(
                '`vocabulary`.`prefix` AS prefix',
                '`vocabulary`.`namespace_uri` AS uri',
                '`vocabulary`.`id` AS id'
            )
            ->from('`vocabulary`', 'vocabulary')
            ->groupBy('`vocabulary`.`id`')
            ->orderBy('`vocabulary`.`id`', 'asc')
            ->addOrderBy('`property`.`id`', 'asc')
        ;
        $result = $this->connection->executeQuery($qb)->fetchAllAssociative();

        static::$vocabularyIdsByPrefixes = array_map('intval', array_column($result, 'id', 'prefix'));
        static::$vocabularyIdsByUris = array_map('intval', array_column($result, 'id', 'uri'));
        static::$vocabularyIdsByPrefixesAndUrisAndIds = static::$vocabularyIdsByPrefixes
            + static::$vocabularyIdsByUris
            + array_combine(static::$vocabularyIdsByPrefixes, static::$vocabularyIdsByPrefixes);
        static::$vocabularyPrefixesByPrefixesAndUrisAndIds = array_column($result, 'prefix', 'prefix')
            + array_column($result, 'prefix', 'uri')
            + array_column($result, 'prefix', 'id');
        static::$vocabularyUrisByPrefixesAndUrisAndIds = array_column($result, 'uri', 'prefix')
            + array_column($result, 'uri', 'uri')
            + array_column($result, 'uri', 'id');
    }
}
