<?php declare(strict_types=1);

namespace Common\Api\Adapter;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

/**
 * Manage common features of api adapter.
 *
 * WARNING: Take care of upgrade process when this trait is used: it must
 * not block access to site, login page, admin board or modules page when it
 * is used. So before use:
 * - check an upgrade of the module that uses this adapter;
 * - check an upgrade of the module Common itself.
 * Then some countermeasures can be taken, generally "require_once" this file
 * at the start of the adapter is enough.
 *
 * Ideally, it should be a core feature.
 *
 * @todo Methods for hydration (see old version of some modules).
 */
trait CommonAdapterTrait
{
    /**
     * @var array
     *
     * @example From module Table:
     * ```php
     * [
     *     'id' => [
     *         'owner_id' => 'owner',
     *     ],
     *      'string' => [
     *          'slug' => 'slug',
     *          'title' => 'title',
     *          'lang' => 'lang',
     *          'source' => 'source',
     *          'comment' => 'comment',
     *      ],
     *      'bool' => [
     *          'is_associative' => 'isAssociative',
     *      ],
     *      'datetime' => [
     *          'created' => ['eq', 'created'],
     *          'created_before' => ['lt', 'created'],
     *          'created_after' => ['gt', 'created'],
     *          'created_until' => ['lte', 'created'],
     *          'created_since' => ['gte', 'created'],
     *          'modified' => ['eq', 'modified'],
     *          'modified_before' => ['lt', 'modified'],
     *          'modified_after' => ['gt', 'modified'],
     *          'modified_until' => ['lte', 'modified'],
     *          'modified_since' => ['gte', 'modified'],
     *     ],
     * ];
     * ```
     *
     * This property must be set in the adapter to use the builder.
     */
    // protected $queryFields = [];

    /**
     * Simplify and manage all common cases for the arguments of the api.
     *
     * @todo May avoid to join the related entity multiple times. Require that the fields are not the same, or pass them here.
     *
     * @return bool True if there is a query on a metadata.
     */
    protected function buildQueryFields(
        QueryBuilder $qb,
        array $query,
        ?string $entityAlias = null,
        ?array $queryFields = null
    ): bool {
        $hasQueryField = false;

        $entityAlias ??= 'omeka_root';
        $queryFields ??= $this->queryFields;

        $expr = $qb->expr();

        foreach ($this->queryFields as $type => $keyFields) foreach (array_intersect_key($keyFields, $query) as $key => $field) {
            if (!isset($query[$key]) || $query[$key] === '' || $query[$key] === []) {
                continue;
            }
            switch ($type) {
                case 'id':
                    // TODO In AbstractResourceEntityAdapter, a join is added for id. It may manage rights, but is it still useful?
                    $hasQueryField = true;
                    $value = $query[$key];
                    $values = is_array($value)
                        ? array_values(array_unique(array_map('intval', $value)))
                        : [(int) $value];
                    if ($values === [0]) {
                        $qb
                            ->andWhere(
                                $expr->isNull($entityAlias . '.' . $field)
                            );
                    } elseif (in_array(0, $values, true)) {
                        $qb
                        ->andWhere($expr->orX(
                            $expr->isNull($entityAlias . '.' . $field),
                            $expr->in(
                                $entityAlias . '.' . $field,
                                // TODO Add the type in Omeka S 4.2. Not required for integers anyway.
                                $this->adapter->createNamedParameter($qb, $values)
                            )
                        ));
                    } elseif (count($values) > 1) {
                        $qb
                            ->andWhere($expr->in(
                                $entityAlias . '.' . $field,
                                $this->adapter->createNamedParameter($qb, $values)
                            ));
                    } else {
                        $qb
                            ->andWhere($expr->eq(
                                $entityAlias . '.' . $field,
                                $this->adapter->createNamedParameter($qb, reset($values))
                            ));
                    }
                    break;

                case 'int':
                    $hasQueryField = true;
                    $value = $query[$key];
                    if (is_array($value)) {
                        $fieldAlias = $this->createAlias();
                        $values = array_values(array_unique(array_map('intval', $value)));
                        $qb
                            ->andWhere($expr->in($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, $values, Connection::PARAM_INT_ARRAY);
                    } else {
                        $value = (int) $value;
                        $qb
                            ->andWhere($expr->eq(
                                $entityAlias . '.' . $field,
                                $this->createNamedParameter($qb, $value, ParameterType::INTEGER)
                            ));
                    }
                    break;

                case 'string':
                    $hasQueryField = true;
                    $value = $query[$key];
                    if (is_array($value)) {
                        $fieldAlias = $this->createAlias();
                        $values = array_values(array_unique(array_map('strval', $value)));
                        $qb
                            ->andWhere($expr->in($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, $values, Connection::PARAM_STR_ARRAY);
                    } else {
                        $value = (string) $value;
                        $qb
                            ->andWhere($expr->eq(
                                $entityAlias . '.' . $field,
                                $this->createNamedParameter($qb, $value, ParameterType::STRING)
                            ));
                    }
                    break;

                case 'bool':
                    if (is_scalar($value)) {
                        $hasQueryField = true;
                        $qb
                            ->andWhere($expr->eq(
                                $entityAlias . '.' . $field,
                                $this->createNamedParameter($qb, $query[$value] ? 1 : 0, ParameterType::INTEGER)
                            ));
                    }
                    break;

                case 'datetime':
                    // TODO Make date use array.
                    // TODO For created and modified, may use a sign like in module Log?
                    /** @see \Log\Api\Adapter\LogAdapter::buildQueryDateComparison() */
                    /** @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildQuery() */
                    // In Omeka Classic, used "since" and "until".
                    $dateGranularities = [
                        DateTime::ISO8601,
                        '!Y-m-d\TH:i:s',
                        '!Y-m-d\TH:i',
                        '!Y-m-d\TH',
                        '!Y-m-d',
                        '!Y-m',
                        '!Y',
                    ];
                    foreach ($dateGranularities as $dateGranularity) {
                        $date = DateTime::createFromFormat($dateGranularity, $value);
                        if (false !== $date) {
                            break;
                        }
                    }
                    $hasQueryField = true;
                    $qb
                        ->andWhere($expr->{$field[0]} (
                            $entityAlias . '.' . $field[1],
                            // If the date is invalid, pass null to ensure no results.
                        $this->createNamedParameter($qb, $date ?: null, $date ? ParameterType::STRING : ParameterType::NULL)
                        ));
                    break;

                default:
                    break;
            }
        }

        return $hasQueryField;
    }

    /**
     * Generic hydration process for common cases.
     *
     * This process does not check request type (create or update).
     * The method shouldHydrate() is not called, so partial update is managed
     * only basically with the presence of keys.
     */
    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
    {
        $data = $request->getContent();
        foreach ($data as $key => $value) {
            $posColon = strpos($key, ':');
            $keyName = $posColon === false ? $key : substr($key, $posColon + 1);
            $method = 'set' . strtr(ucwords($keyName, ' _-'), [' ' => '', '_' => '', '-' => '']);
            if (method_exists($entity, $method)) {
                $entity->$method($value);
            }
        }
    }
}
