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
 * WARNING: This file is included automatically with TraitModule, but for more
 * security on running instance of Omeka, take care of upgrade process when this
 * trait is used: it must not block access to site, login page, admin board or
 * modules page when it is used. So:
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
     */
    protected $operatorsSql = [
        '<' => '<',
        '≤' => '<=',
        '≥' => '>=',
        '>' => '>',
        '=' => '=',
        '≠' => '<>',
        /*
        // Keys with more than one character allows to manage internal cases.
        // No more used: the process requires one character only.
        '<=' => '<=',
        '>=' => '>=',
        '<>' => '<>',
        'lt' => '<',
        'lte' => '<=',
        'gte' => '>=',
        'gt' => '>',
        'eq' => '=',
        'neq' => '<>',
        */
        /* @todo Use operators ∃ and ∄ (\u2203/\u2204) for is not null/is null.
        '∄' => 'IS NULL',
        '∃' => 'IS NOT NULL',
        */
    ];

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
     *      'int_operator' => [
     *          'count' => 'count',
     *      ],
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
     *          'created_before' => ['<', 'created'],
     *          'created_after' => ['>', 'created'],
     *          'created_until' => ['≤', 'created'],
     *          'created_since' => ['≥', 'created'],
     *          'modified_before' => ['<', 'modified'],
     *          'modified_after' => ['>', 'modified'],
     *          'modified_until' => ['≤', 'modified'],
     *          'modified_since' => ['≥', 'modified'],
     *     ],
     *      'datetime_operator' => [
     *          'created' => 'created',
     *          'modified' => 'modified',
     *      ],
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
     * - For datetime, it is simpler to use the mathematical operators that are
     *   more versatile, but the key names are used for compatibility with omeka
     *   main adapter. In Omeka Classic, since" and "until" were used.
     * - "datetime_operator" is limited to dates between -9999 and 9999. For
     *   older dates, an integer is required. Note that mysql allows only dates
     *   between 1000 and 9999, so a literal column is required for older dates.
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
     * So a loop and a switch allow to build query in all modules and to manage
     * empty, single or multiple values for any field.
     *
     * @todo May avoid to join the related entity multiple times. Require that the fields are not the same, or pass them here.
     *
     * @return bool True if there is at least one valid query on a metadata.
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

        // TODO Using "Expr" is generally useless, because it's just a string builder. Replacing with string concatenation avoids some calls.

        foreach ($queryFields as $type => $keyFields) foreach (array_intersect_key($keyFields, $query) as $key => $field) {
            if (!isset($query[$key]) || $query[$key] === '' || $query[$key] === []) {
                continue;
            }
            $hasQueryField = true;
            $value = $query[$key];
            // TODO Add type in Omeka S 4.2 with createNameParameter(). Not required anyway.
            switch ($type) {
                case 'bool':
                    $fieldAlias = $this->createAlias();
                    if (is_scalar($value)) {
                        $qb
                            ->andWhere($entityAlias . '.' . $field . ' = :' . $fieldAlias)
                            // TODO A boolean parameter type can be used.
                            ->setParameter($fieldAlias, $value ? 1 : 0, ParameterType::INTEGER);
                    } else {
                        $qb
                            ->andWhere($entityAlias . '.' . $field . ' = "bad value"');
                    }
                    break;

                case 'id':
                    // Unlike main AbstractEntityAdapter, an id may be "0" to search empty values.
                    // TODO In AbstractResourceEntityAdapter, a join is added for id. It may manage rights, but is it still useful?
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
                            ->andWhere($entityAlias . '.' . $field . ' = :' . $fieldAlias)
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
                    $fieldAlias = $this->createAlias();
                    if (is_array($value)) {
                        $values = array_values(array_unique(array_map('intval', $value)));
                        $qb
                            ->andWhere($expr->in($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, $values, Connection::PARAM_INT_ARRAY);
                    } else {
                        $value = (int) $value;
                        $qb
                            ->andWhere($entityAlias . '.' . $field . ' = :' . $fieldAlias)
                            ->setParameter($fieldAlias, $value, ParameterType::INTEGER);
                    }
                    break;

                case 'int_empty':
                    $values = is_array($value)
                        ? array_values(array_unique(array_map('intval', $value)))
                        : [(int) $value];
                    $fieldAlias = $this->createAlias();
                    if ($values === [0]) {
                        // Unlike "id", a value may be 0.
                        $qb
                            ->andWhere($expr->orX(
                                $expr->isNull($entityAlias . '.' . $field),
                                $entityAlias . '.' . $field . ' = :' . $fieldAlias
                            ))
                            ->setParameter($fieldAlias, 0, ParameterType::INTEGER);
                    } elseif (count($values) === 1) {
                        $qb
                            ->andWhere($entityAlias . '.' . $field . ' = :' . $fieldAlias)
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

                case 'int_operator':
                    // There is no optimization with sql "between" in lieu of
                    // </≤ and >/≥. It can be done in adapter if really needed.
                    $values = is_array($value) ? $value : [$value];
                    // Simplify the list of values by operator.
                    $opVals = [];
                    foreach ($values as $value) {
                        $operator = mb_substr((string) $value, 0, 1);
                        if (is_numeric($operator) || $operator === '-') {
                            $opVals['='][] = (int) $value;
                        } elseif ($operator === '=') {
                            $opVals['='][] = (int) mb_substr((string) $value, 1);
                        } elseif ($operator === '≠') {
                            $opVals['<>'][] = (int) mb_substr((string) $value, 1);
                        } elseif (isset($this->operatorsSql[$operator])) {
                            $opVals[$this->operatorsSql[$operator]][] = (int) mb_substr($value, 1);
                        } else {
                            // Unknown operator means error, so no result.
                            $qb
                                ->andWhere('"bad" = "operator"');
                            break;
                        }
                    }
                    foreach ($opVals as $opSql => $vals) {
                        $fieldAlias = $this->createAlias();
                        if ($opSql === '=') {
                            if (count($vals) === 1) {
                                $qb
                                    ->andWhere($entityAlias . '.' . $field . ' = :' . $fieldAlias)
                                    ->setParameter($fieldAlias, reset($vals), ParameterType::INTEGER);
                            } else {
                                $qb
                                    ->andWhere($expr->in($entityAlias . '.' . $field, ':' . $fieldAlias))
                                    ->setParameter($fieldAlias, $vals, Connection::PARAM_INT_ARRAY);
                            }
                        } elseif ($opSql === '<>') {
                            if (count($vals) === 1) {
                                $qb
                                    ->andWhere($entityAlias . '.' . $field . ' <> :' . $fieldAlias)
                                    ->setParameter($fieldAlias, reset($vals), ParameterType::INTEGER);
                            } else {
                                $qb
                                    ->andWhere($expr->notIn($entityAlias . '.' . $field, ':' . $fieldAlias))
                                    ->setParameter($fieldAlias, $vals, Connection::PARAM_INT_ARRAY);
                            }
                        } else {
                            $val = $opSql === '<' || $opSql === '<=' ? min($vals) : max($vals);
                            $qb
                                // Comparison is just a string concatenation.
                                // ->andWhere(new Comparison($entityAlias . '.' . $field, $opSql, ':' . $fieldAlias))
                                ->andWhere($entityAlias . '.' . $field . ' ' . $opSql . ' :' . $fieldAlias)
                                ->setParameter($fieldAlias, $val, ParameterType::INTEGER);
                        }
                    }
                    break;

                case 'string':
                    $fieldAlias = $this->createAlias();
                    if (is_array($value)) {
                        $values = array_values(array_unique(array_map('strval', $value)));
                        $qb
                            ->andWhere($expr->in($entityAlias . '.' . $field, ':' . $fieldAlias))
                            ->setParameter($fieldAlias, $values, Connection::PARAM_STR_ARRAY);
                    } else {
                        $value = (string) $value;
                        $qb
                            ->andWhere($entityAlias . '.' . $field . ' = :' . $fieldAlias)
                            ->setParameter($fieldAlias, $value, ParameterType::STRING);
                    }
                    break;

                case 'string_empty':
                    $values = is_array($value)
                        ? array_values(array_unique(array_map('strval', $value)))
                        : [(string) $value];
                    $fieldAlias = $this->createAlias();
                    if ($values === ["''"]) {
                        $qb
                            ->andWhere($expr->orX(
                                $expr->isNull($entityAlias . '.' . $field),
                                $entityAlias . '.' . $field . ' = :' . $fieldAlias
                            ))
                            ->setParameter($fieldAlias, '', ParameterType::STRING);
                    } elseif (count($values) === 1) {
                        $qb
                            ->andWhere($entityAlias . '.' . $field . ' = :' . $fieldAlias)
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

                case 'string_operator':
                    // There is no optimization with sql "between" in lieu of
                    // </≤ and >/≥. It can be done in adapter if really needed.
                    $values = is_array($value) ? $value : [$value];
                    // Simplify the list of values by operator.
                    $opVals = [];
                    foreach ($values as $value) {
                        $operator = mb_substr((string) $value, 0, 1);
                        if ($operator === '=') {
                            $opVals['='][] = mb_substr((string) $value, 1);
                        } elseif ($operator === '≠') {
                            $opVals['<>'][] = mb_substr((string) $value, 1);
                        } elseif (isset($this->operatorsSql[$operator])) {
                            $opVals[$this->operatorsSql[$operator]][] = mb_substr((string) $value, 1);
                        } else {
                            $opVals['='][] = (string) $value;
                        }
                    }
                    foreach ($opVals as $opSql => $vals) {
                        $fieldAlias = $this->createAlias();
                        if ($opSql === '=') {
                            if (count($vals) === 1) {
                                $qb
                                    ->andWhere($entityAlias . '.' . $field . ' = :' . $fieldAlias)
                                    ->setParameter($fieldAlias, reset($vals), ParameterType::STRING);
                            } else {
                                $qb
                                    ->andWhere($expr->in($entityAlias . '.' . $field, ':' . $fieldAlias))
                                    ->setParameter($fieldAlias, $vals, Connection::PARAM_STR_ARRAY);
                            }
                        } elseif ($opSql === '<>') {
                            if (count($vals) === 1) {
                                $qb
                                    ->andWhere($entityAlias . '.' . $field . ' <> :' . $fieldAlias)
                                    ->setParameter($fieldAlias, reset($vals), ParameterType::STRING);
                            } else {
                                $qb
                                ->andWhere($expr->notIn($entityAlias . '.' . $field, ':' . $fieldAlias))
                                ->setParameter($fieldAlias, $vals, Connection::PARAM_STR_ARRAY);
                            }
                        } else {
                            $val = $opSql === '<' || $opSql === '<=' ? min($vals) : max($vals);
                            $qb
                                // Comparison is just a string concatenation.
                                // ->andWhere(new Comparison($entityAlias . '.' . $field, $opSql, ':' . $fieldAlias))
                                ->andWhere($entityAlias . '.' . $field . ' ' . $opSql . ' :' . $fieldAlias)
                                ->setParameter($fieldAlias, $val, ParameterType::STRING);
                        }
                    }
                    break;

                case 'datetime':
                    $value = $field[0] . $value;
                    $field = $field[1];
                    // No break.
                case 'datetime_operator':
                    // There is no optimization when there are multiple times
                    // the same operator <≤=≠≥>, because it is rare. It may be
                    // done earlier in adapter if really needed.
                    // There is no optimization with sql "between" in lieu of
                    // </≤ and >/≥. It can be done earlier in adapter if needed.
                    // Warning: if the column is an sql datetime, the year must
                    // be between 1000 and 9999.
                    $values = is_array($value) ? $value : [$value];
                    foreach ($values as $value) {
                        $operator = mb_substr((string) $value, 0, 1);
                        if (is_numeric($operator) || $operator === '-') {
                            $operator = '=';
                        } elseif (isset($this->operatorsSql[$operator])) {
                            $value = mb_substr((string) $value, 1);
                        } else {
                            // Unknown operator means error, so no result.
                            $qb
                                ->andWhere('"bad" = "operator"');
                            continue;
                        }
                        $value = $this->dateComplete($value, $operator);
                        if (!$value) {
                            // Empty date means error, so no result.
                            $qb
                                ->andWhere('"bad" = "date"');
                        } elseif (is_array($value)) {
                            $fieldAliasFrom = $this->createAlias();
                            $fieldAliasTo = $this->createAlias();
                            if ($operator === '=') {
                                $qb->andWhere($entityAlias . '.' . $field . ' BETWEEN :' . $fieldAliasFrom . ' AND :' . $fieldAliasTo);
                            } else {
                                $qb->andWhere('NOT(' . $entityAlias . '.' . $field . ' BETWEEN :' . $fieldAliasFrom . ' AND :' . $fieldAliasTo . ')');
                            }
                            $qb
                                ->setParameter($fieldAliasFrom, $value['from'], ParameterType::STRING)
                                ->setParameter($fieldAliasTo, $value['to'], ParameterType::STRING);
                        } else {
                            $fieldAlias = $this->createAlias();
                            $qb
                                ->andWhere($entityAlias . '.' . $field . ' ' . $this->operatorsSql[$operator] . ' :' . $fieldAlias)
                                ->setParameter($fieldAlias, $value, ParameterType::STRING);
                        }
                    }
                    break;

                default:
                    break;
            }
        }

        return $hasQueryField;
    }

    /**
     * Format a full or partial date for search.
     *
     * Min/max year is 10000. In other cases, an integer is enough.
     * Note that in sql, dates can be only between 1000 and 9999.
     *
     * A DateTime is formatted 'Y-m-d H:i:s.u' in doctrine, whatever initial
     * precision. Missing parts are replaced by 1 (date) or 0 (time).
     * So it is usable only to compare full date time (year may be negative).
     * For other values, the function completes the missing parts with start or
     * end of the existing part according to the operator.
     *
     * @see \Doctrine\DBAL\Platforms\SQLAnywherePlatform::getDateTimeFormatString()
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildQuery()
     *
     * @param string|DateTime $value
     * @param string $operator One character math operator: <≤=≠≥>.
     * @return string|array|null The output may be array to manage partial date.
     */
    protected function dateComplete($value, string $operator = '=')
    {
        if ($value instanceof DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        // Support iso value, but convert it to mysql.
        $value = trim(strtr((string) $value, 'T', ' '));
        if (!$value) {
            return null;
        }

        $isNegative = mb_substr($value, 0, 1) === '-';
        if ($isNegative) {
            $value = mb_substr($value, 1);
        }

        // Pad the year with leading zeros if less than 4 digits.
        $parts = explode('-', $value, 2);
        if (isset($parts[0]) && is_numeric($parts[0]) && mb_strlen($parts[0]) < 4) {
            $parts[0] = str_pad($parts[0], 4, '0', STR_PAD_LEFT);
            $value = implode('-', $parts);
        }

        if (mb_strlen($value) >= 19) {
            return $isNegative ? '-' . $value : $value;
        }

        // Complete each part as min or max according to operator.
        $dateMin = '0000-01-01 00:00:00';
        $dateMax = '9999-12-31 23:59:59';

        if ($operator === '<') {
            $value = substr_replace($dateMin, $value, 0, mb_strlen($value) - 19);
        } elseif ($operator === '≤') {
            $value = substr_replace($dateMax, $value, 0, mb_strlen($value) - 19);
        } elseif ($operator === '≥') {
            $value = substr_replace($dateMin, $value, 0, mb_strlen($value) - 19);
        } elseif ($operator === '>') {
            $value = substr_replace($dateMax, $value, 0, mb_strlen($value) - 19);
        } elseif ($operator === '=' || $operator === '≠') {
            $valueFrom = substr_replace($dateMin, $value, 0, mb_strlen($value) - 19);
            $valueTo = substr_replace($dateMax, $value, 0, mb_strlen($value) - 19);
            return $isNegative
                ? ['from' => '-' . $valueFrom, 'to' => '-' . $valueTo]
                : ['from' => $valueFrom, 'to' => $valueTo];
        } else {
            return null;
        }
        return $isNegative ? '-' . $value : $value;
    }

    /**
     * Generic hydration process for common cases.
     *
     * This process does not check request type (create or update).
     * The method shouldHydrate() is not called, so partial update is managed
     * only basically with the presence of keys.
     */
    protected function hydrateAuto(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
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
