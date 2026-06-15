<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \\/       \\//       \//       \
 *  /        //        ///        //      __/
 * /         /         /        _/        _/
 * \__/__/__/\________/\____/___/\_______/
 *
 * This program is licensed under the CSSM Unlimited License v2.0.
 * Copyright (c) 2024 Sergiy Chernega
 *
 * @author CSSM Group
 * @link https://cssm.pp.ua/
 *
 *
 */

declare(strict_types=1);

namespace morfeditorial\builders;

use morfeditorial\interfaces\QueryBuilderInterface;

class SQLQueryBuilder extends BaseQueryBuilder
{
    private PDO $connection;

    private array $params = [];

    private int $paramCounter = 0;

    public function __construct(PDO $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    private function bindParameters(PDOStatement $stmt) : void
    {
        foreach ($this->params as $key => $value) {
            $type = match (gettype($value)) {
                'integer' => PDO::PARAM_INT,
                'boolean' => PDO::PARAM_BOOL,
                'NULL' => PDO::PARAM_NULL,
                default => PDO::PARAM_STR
            };

            $stmt->bindValue($key, $value, $type);
        }
    }

    public function execute() : void
    {
        $this->params = [];
        $this->paramCounter = 0;
        $sql = $this->buildQuery();
        $stmt = $this->connection->prepare($sql);
        $this->bindParameters($stmt);
        $stmt->execute();
    }

    public function get() : array
    {
        $this->params = [];
        $this->paramCounter = 0;
        $sql = $this->buildQuery();
        $stmt = $this->connection->prepare($sql);
        $this->bindParameters($stmt);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function first() : ?array
    {
        $this->limit(1);
        $result = $this->get();

        return ! empty($result) ? $result[0] : null;
    }

    public function whereRaw(string $rawCondition, array $bindings = []) : QueryBuilderInterface
    {
        $uniqueKey = uniqid('raw_', true);
        foreach ($bindings as $key => $value) {
            $paramName = ":{$uniqueKey}_{$key}";
            $this->params[$paramName] = $value;
            $rawCondition = str_replace(":{$key}", $paramName, $rawCondition);
        }

        $this->currentConditionGroup->addCondition(
            $rawCondition,
            'RAW',
            null,
            ConditionGroup::TYPE_AND
        );

        return $this;
    }

    private function buildQuery() : string
    {
        switch ($this->query['type']) {
            case 'select':
                return $this->buildSelectQuery();
            case 'insert':
                return $this->buildInsertQuery();
            case 'update':
                return $this->buildUpdateQuery();
            case 'delete':
                return $this->buildDeleteQuery();
            default:
                throw new RuntimeException('Unknown query type');
        }
    }

    private function buildSelectQuery() : string
    {
        $columns = is_array($this->query['columns'])
            ? implode(', ', $this->query['columns'])
            : $this->query['columns'];

        $sql = "SELECT {$columns} FROM {$this->query['table']}";

        // JOIN
        if (! empty($this->query['joins'])) {
            foreach ($this->query['joins'] as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }

        // WHERE conditions
        if (! $this->whereConditions->isEmpty()) {
            $sql .= ' WHERE ' . $this->buildWhereClause($this->whereConditions);
        }

        // GROUP BY
        if (! empty($this->query['group'])) {
            $sql .= ' GROUP BY ' . implode(', ', $this->query['group']);
        }

        // HAVING
        if (isset($this->query['having']) && ! $this->query['having']->isEmpty()) {
            $sql .= ' HAVING ' . $this->buildWhereClause($this->query['having'], true, 'having');
        }

        // ORDER BY
        if (! empty($this->query['order'])) {
            $orderClauses = [];
            foreach ($this->query['order'] as $order) {
                $orderClauses[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        // LIMIT and OFFSET
        if (null !== $this->query['limit']) {
            $sql .= " LIMIT {$this->query['limit']}";
        }

        if (null !== $this->query['offset']) {
            $sql .= " OFFSET {$this->query['offset']}";
        }

        return $sql;
    }

    private function buildInsertQuery() : string
    {
        $columns = implode(', ', array_keys($this->query['data']));
        $placeholders = implode(', ', array_map(function ($key) {
            $paramName = ":insert_{$key}";
            $this->params[$paramName] = $this->query['data'][$key];

            return $paramName;
        }, array_keys($this->query['data'])));

        return "INSERT INTO {$this->query['table']} ({$columns}) VALUES ({$placeholders})";
    }

    private function buildUpdateQuery() : string
    {
        $setClauses = [];
        foreach ($this->query['data'] as $column => $value) {
            $paramName = ":update_{$column}";
            $this->params[$paramName] = $value;
            $setClauses[] = "{$column} = {$paramName}";
        }

        $sql = "UPDATE {$this->query['table']} SET " . implode(', ', $setClauses);

        if (! $this->whereConditions->isEmpty()) {
            $sql .= ' WHERE ' . $this->buildWhereClause($this->whereConditions);
        }

        return $sql;
    }

    private function buildDeleteQuery() : string
    {
        $sql = "DELETE FROM {$this->query['table']}";

        if (! $this->whereConditions->isEmpty()) {
            $sql .= ' WHERE ' . $this->buildWhereClause($this->whereConditions);
        }

        return $sql;
    }

    private function buildWhereClause(ConditionGroup $group, bool $isRoot = true, string $prefix = 'where') : string
    {
        $conditions = $group->getConditions();
        if (empty($conditions)) {
            return '';
        }

        $sql = [];
        $isFirst = true;

        foreach ($conditions as $condition) {
            $boolean = $isFirst ? '' : " {$condition['boolean']} ";
            $isFirst = false;

            if ('condition' === $condition['type']) {
                if ('BETWEEN' === $condition['operator']) {
                    $minParam = ":{$prefix}_" . $this->paramCounter++;
                    $maxParam = ":{$prefix}_" . $this->paramCounter++;
                    $this->params[$minParam] = $condition['value'][0];
                    $this->params[$maxParam] = $condition['value'][1];
                    $sql[] = $boolean . "{$condition['column']} BETWEEN $minParam AND $maxParam";
                } elseif ('NOT BETWEEN' === $condition['operator']) {
                    $minParam = ":{$prefix}_" . $this->paramCounter++;
                    $maxParam = ":{$prefix}_" . $this->paramCounter++;
                    $this->params[$minParam] = $condition['value'][0];
                    $this->params[$maxParam] = $condition['value'][1];
                    $sql[] = $boolean . "{$condition['column']} NOT BETWEEN $minParam AND $maxParam";
                } elseif ('EXISTS' === $condition['operator']) {
                    $subQuery = $condition['value']->buildQuery();
                    $sql[] = $boolean . "EXISTS ({$subQuery})";
                } elseif ('NOT EXISTS' === $condition['operator']) {
                    $subQuery = $condition['value']->buildQuery();
                    $sql[] = $boolean . "NOT EXISTS ({$subQuery})";
                } elseif ('RAW' === $condition['operator']) {
                    $sql[] = $boolean . "({$condition['column']})";
                } else {
                    $paramName = ":{$prefix}_" . $this->paramCounter++;
                    $this->params[$paramName] = $condition['value'];
                    $sql[] = $boolean . "({$condition['column']} {$condition['operator']} {$paramName})";
                }
            } elseif ('group' === $condition['type']) {
                $subGroup = $this->buildWhereClause($condition['group'], false, $prefix);
                $sql[] = $boolean . "({$subGroup})";
            }
        }

        return implode(' ', $sql);
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER') : QueryBuilderInterface
    {
        $this->query['joins'][] = [
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'type' => $type,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second) : QueryBuilderInterface
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second) : QueryBuilderInterface
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function groupBy(string ...$columns) : QueryBuilderInterface
    {
        $this->query['group'] = array_merge($this->query['group'] ?? [], $columns);

        return $this;
    }

    public function having(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface
    {
        if (null === $value) {
            $value = $operator;
            $operator = '=';
        }

        if (! isset($this->query['having'])) {
            $this->query['having'] = new ConditionGroup();
        }

        $this->query['having']->addCondition(
            $column,
            $operator,
            $value,
            ConditionGroup::TYPE_AND
        );

        return $this;
    }

    public function orHaving(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface
    {
        if (null === $value) {
            $value = $operator;
            $operator = '=';
        }

        if (! isset($this->query['having'])) {
            $this->query['having'] = new ConditionGroup();
        }

        $this->query['having']->addCondition(
            $column,
            $operator,
            $value,
            ConditionGroup::TYPE_OR
        );

        return $this;
    }

    public function count(string $column = '*') : int
    {
        $original = $this->query['columns'];
        $this->query['columns'] = "COUNT({$column}) as aggregate";
        $result = $this->get();
        $this->query['columns'] = $original;

        return (int) ($result[0]['aggregate'] ?? 0);
    }

    public function sum(string $column) : float
    {
        $original = $this->query['columns'];
        $this->query['columns'] = "SUM({$column}) as aggregate";
        $result = $this->get();
        $this->query['columns'] = $original;

        return (float) ($result[0]['aggregate'] ?? 0);
    }

    public function avg(string $column) : float
    {
        $original = $this->query['columns'];
        $this->query['columns'] = "AVG({$column}) as aggregate";
        $result = $this->get();
        $this->query['columns'] = $original;

        return (float) ($result[0]['aggregate'] ?? 0);
    }

    public function min(string $column) : mixed
    {
        $original = $this->query['columns'];
        $this->query['columns'] = "MIN({$column}) as aggregate";
        $result = $this->get();
        $this->query['columns'] = $original;

        return $result[0]['aggregate'] ?? null;
    }

    public function max(string $column) : mixed
    {
        $original = $this->query['columns'];
        $this->query['columns'] = "MAX({$column}) as aggregate";
        $result = $this->get();
        $this->query['columns'] = $original;

        return $result[0]['aggregate'] ?? null;
    }

    public function paginate(int $perPage = 15, int $page = 1) : array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $originalLimit = $this->query['limit'];
        $originalOffset = $this->query['offset'];

        $total = $this->count();

        $this->limit($perPage);
        $this->offset($offset);

        $items = $this->get();

        $this->query['limit'] = $originalLimit;
        $this->query['offset'] = $originalOffset;

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => $offset + count($items),
            ],
        ];
    }
}
