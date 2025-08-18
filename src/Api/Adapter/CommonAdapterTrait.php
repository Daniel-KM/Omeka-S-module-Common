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
     * WARNING: This property must be set in the adapter to use the builder.
     *
     * @var array
     *
     * @example From module Table (adapted to display more possibilities):
     * ```php
     * [
     *     'id' => [
     *         'owner_id' => 'owner',
     *     ],
     *     'int' => [
     *         'total' => 'total',
     *     ],
     *      'string' => [
     *          'slug' => 'slug',
     *          'title' => 'title',
     *          'source' => 'source',
     *          'comment' => 'comment',
     *      ],
     *      'string_empty' => [
     *          'lang' => 'lang',
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
     * Notes:
     * - "string" and "string_empty" are the same, but with "search_empty",
     *   there is a convention for the value double single quote ('') that means
     *   to search empty string or null.
     *   Note that values are casted first to string in all cases, like any
     *   query arguments anyway.
     * - "int" and "int_empty" are the same, but with "int_empty", the value
     *   zero (0) means to search empty values (0 or null).
     *   Note that values are casted first to integer in all cases.
     * - "id" is like a simplified "int_empty", because the id is never 0.
     *   Furthermore, a join may be added in a future version if really needed.
     *
     * For now, there is no way to manage the difference between empty value
     * (no-length string or 0) and no value (null). It is useless most of the
     * time, depends on the column format (accept null or not), and any specific
     * rules can be added in any adapter anyway.
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

        foreach ($queryFields as $type => $keyFields) foreach (array_intersect_key($keyFields, $query) as $key => $field) {
            if (!isset($query[$key]) || $query[$key] === '' || $query[$key] === []) {
                continue;
            }
            // TODO Add type in Omeka S 4.2 with createNameParameter(). Not required anyway.
            switch ($type) {
                case 'id':
                    // Unlike main AbstractEntityAdapter, an id may be "0" to search empty values.
                    // TODO In AbstractResourceEntityAdapter, a join is added for id. It may manage rights, but is it still useful?
                    $hasQueryField = true;
                    $value = $query[$key];
                    $values = is_array($value)
                        ? array_values(array_unique(array_map('intval', $value)))
                        : [(int) $value];
                    if ($values === [0]) {
                        // Unlike "int_empty", an "id" is never 0.
                        $qb
                            ->andWhere($expr->isNull($entityAlias . '.' . $field));
                    } elseif (count($values) === 1) {
                        $fieldAlias = $this->createAlias();
                        $qb
                            ->andWhere($expr->eq($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, reset($values), ParameterType::INTEGER);
                    } elseif (in_array(0, $values, true)) {
                        $fieldAlias = $this->createAlias();
                        $qb
                            ->andWhere($expr->orX(
                                $expr->isNull($entityAlias . '.' . $field),
                                $expr->in($entityAlias . '.' . $field, ':' . $fieldAlias)
                            ))
                            ->setParameter($fieldAlias, $values, Connection::PARAM_INT_ARRAY);
                    } else {
                        $fieldAlias = $this->createAlias();
                        $qb
                            ->andWhere($expr->in($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, $values, Connection::PARAM_INT_ARRAY);
                    }
                    break;

                case 'int':
                    $hasQueryField = true;
                    $value = $query[$key];
                    $fieldAlias = $this->createAlias();
                    if (is_array($value)) {
                        $values = array_values(array_unique(array_map('intval', $value)));
                        $qb
                            ->andWhere($expr->in($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, $values, Connection::PARAM_INT_ARRAY);
                    } else {
                        $value = (int) $value;
                        $qb
                            ->andWhere($expr->eq($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, $value, ParameterType::INTEGER);
                    }
                    break;

                case 'int_empty':
                    $hasQueryField = true;
                    $value = $query[$key];
                    $values = is_array($value)
                        ? array_values(array_unique(array_map('intval', $value)))
                        : [(int) $value];
                    $fieldAlias = $this->createAlias();
                    if ($values === [0]) {
                        // Unlike "id", a value may be 0.
                        $qb
                            ->andWhere($expr->orX(
                                $expr->isNull($entityAlias . '.' . $field),
                                $expr->eq($entityAlias . '.' . $field, ':' . $fieldAlias)
                            ))
                            ->setParameter($fieldAlias, 0, ParameterType::INTEGER);
                    } elseif (count($values) === 1) {
                        $qb
                            ->andWhere($expr->eq($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, reset($values), ParameterType::INTEGER);
                    } elseif (in_array(0, $values, true)) {
                        $qb
                            ->andWhere($expr->orX(
                                $expr->isNull($entityAlias . '.' . $field),
                                $expr->in($entityAlias . '.' . $field, ':' . $fieldAlias)
                            ))
                            ->setParameter($fieldAlias, $values, Connection::PARAM_INT_ARRAY);
                    } else {
                        $qb
                            ->andWhere($expr->in($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, $values, Connection::PARAM_INT_ARRAY);
                    }
                    break;

                case 'string':
                    $hasQueryField = true;
                    $value = $query[$key];
                    $fieldAlias = $this->createAlias();
                    if (is_array($value)) {
                        $values = array_values(array_unique(array_map('strval', $value)));
                        $qb
                            ->andWhere($expr->in($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, $values, Connection::PARAM_STR_ARRAY);
                    } else {
                        $value = (string) $value;
                        $qb
                            ->andWhere($expr->eq($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, $value, ParameterType::STRING);
                    }
                    break;

                case 'string_empty':
                    $hasQueryField = true;
                    $value = $query[$key];
                    $values = is_array($value)
                        ? array_values(array_unique(array_map('strval', $value)))
                        : [(string) $value];
                    $fieldAlias = $this->createAlias();
                    if ($values === ["''"]) {
                        $qb
                            ->andWhere($expr->orX(
                                $expr->isNull($entityAlias . '.' . $field),
                                $expr->eq($entityAlias . '.' . $field, ':' . $fieldAlias)
                            ))
                            ->setParameter($fieldAlias, '', ParameterType::STRING);
                    } elseif (count($values) === 1) {
                        $qb
                            ->andWhere($expr->eq($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, reset($values), ParameterType::STRING);
                    } elseif (($posEmpty = array_search("''", $values, true)) !== false) {
                        // Manage the convention for empty string. There may be
                        // another native empty string in the list of values,
                        // but it doesn't matter.
                        $values[$posEmpty] = '';
                        $qb
                            ->andWhere($expr->orX(
                                $expr->isNull($entityAlias . '.' . $field),
                                $expr->in($entityAlias . '.' . $field, ':' . $fieldAlias)
                            ))
                            ->setParameter($fieldAlias, $values, Connection::PARAM_STR_ARRAY);
                    } else {
                        $qb
                            ->andWhere($expr->in($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, $values, Connection::PARAM_STR_ARRAY);
                    }
                    break;

                case 'bool':
                    if (is_scalar($value)) {
                        $hasQueryField = true;
                        $fieldAlias = $this->createAlias();
                        $qb
                            ->andWhere($expr->eq($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, $query[$value] ? 1 : 0, ParameterType::INTEGER);
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
                    $fieldAlias = $this->createAlias();
                    $qb
                        ->andWhere($expr->{$field[0]}($entityAlias . '.' . $field[1], ':' . $fieldAlias))
                        // If the date is invalid, pass null to ensure no results.
                        ->setParameter($fieldAlias, $date ? (string) $date : null, $date ? ParameterType::STRING : ParameterType::NULL);
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
