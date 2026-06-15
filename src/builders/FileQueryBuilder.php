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

class FileQueryBuilder extends BaseQueryBuilder
{
    private array $data;

    public function __construct(array &$data)
    {
        parent::__construct();
        $this->data = &$data;
    }

    public function insert(string $table, array $data) : QueryBuilderInterface
    {
        $this->validateData($data);

        return parent::insert($table, $data);
    }

    public function execute() : void
    {
        switch ($this->query['type']) {
            case 'insert':
                $this->executeInsert();
                break;
            case 'update':
                $this->executeUpdate();
                break;
            case 'delete':
                $this->executeDelete();
                break;
            default:
                throw new RuntimeException('Invalid operation for execute()');
        }
    }

    public function get() : array
    {
        if ('select' !== $this->query['type']) {
            throw new RuntimeException('get() can only be called on select queries');
        }

        $table = $this->query['table'];

        if (! isset($this->data[$table])) {
            $this->data[$table] = [];

            return [];
        }

        $resultData = $this->data[$table];
        if (! empty($this->query['joins'])) {
            foreach ($this->query['joins'] as $join) {
                $joinTable = $join['table'];
                if (! isset($this->data[$joinTable])) {
                    $this->data[$joinTable] = [];
                }

                $joinData = $this->data[$joinTable];
                $firstParts = explode('.', $join['first']);
                $secondParts = explode('.', $join['second']);

                $firstTable = count($firstParts) > 1 ? $firstParts[0] : $table;
                $firstColumn = count($firstParts) > 1 ? $firstParts[1] : $firstParts[0];

                $secondTable = count($secondParts) > 1 ? $secondParts[0] : $joinTable;
                $secondColumn = count($secondParts) > 1 ? $secondParts[1] : $secondParts[0];

                $tempResult = [];
                foreach ($resultData as $row) {
                    $matched = false;
                    foreach ($joinData as $joinRow) {
                        if ($this->evaluateJoinCondition($row, $joinRow, $firstColumn, $join['operator'], $secondColumn)) {
                            $merged = array_merge(
                                $row,
                                array_combine(
                                    array_map(fn ($key) => $joinTable . '.' . $key, array_keys($joinRow)),
                                    $joinRow
                                )
                            );
                            $tempResult[] = $merged;
                            $matched = true;
                        }
                    }

                    if (! $matched && in_array($join['type'], ['LEFT', 'RIGHT'])) {
                        if (('LEFT' === $join['type'] && $firstTable === $table) ||
                            ('RIGHT' === $join['type'] && $secondTable === $joinTable)) {
                            $nullJoinData = array_combine(
                                array_map(fn ($key) => $joinTable . '.' . $key, array_keys(reset($joinData) ?: [])),
                                array_fill(0, count(reset($joinData) ?: []), null)
                            );
                            $tempResult[] = array_merge($row, $nullJoinData ?: []);
                        }
                    }
                }
                $resultData = $tempResult;
            }
        }

        $filteredData = array_filter($resultData, fn ($row) => $this->evaluateConditionGroup($this->whereConditions, $row));

        if (! empty($this->query['group'])) {
            $groupedData = [];
            foreach ($filteredData as $row) {
                $groupKey = '';
                foreach ($this->query['group'] as $column) {
                    $groupKey .= (isset($row[$column]) ? $row[$column] : 'null') . '|';
                }

                if (! isset($groupedData[$groupKey])) {
                    $groupData = [];
                    foreach ($this->query['group'] as $column) {
                        $groupData[$column] = $row[$column] ?? null;
                    }
                    $groupedData[$groupKey] = $groupData;
                }
            }
            $filteredData = array_values($groupedData);
        }

        if (isset($this->query['having']) && ! $this->query['having']->isEmpty()) {
            $filteredData = array_filter($filteredData, fn ($row) => $this->evaluateConditionGroup($this->query['having'], $row));
        }

        if (! empty($this->query['order'])) {
            usort($filteredData, function ($a, $b) {
                foreach ($this->query['order'] as $order) {
                    $column = $order['column'];
                    $direction = $order['direction'];

                    if (! isset($a[$column]) || ! isset($b[$column])) {
                        continue;
                    }

                    if ($a[$column] == $b[$column]) {
                        continue;
                    }

                    $result = $a[$column] <=> $b[$column];

                    return 'ASC' === $direction ? $result : -$result;
                }

                return 0;
            });
        }

        if (null !== $this->query['offset']) {
            $filteredData = array_slice($filteredData, $this->query['offset']);
        }

        if (null !== $this->query['limit']) {
            $filteredData = array_slice($filteredData, 0, $this->query['limit']);
        }

        if ('*' !== $this->query['columns'] && is_array($this->query['columns'])) {
            return array_map(function ($row) {
                $result = [];
                foreach ($this->query['columns'] as $column) {
                    if (isset($row[$column])) {
                        $result[$column] = $row[$column];
                    }
                }

                return $result;
            }, $filteredData);
        }

        return array_values($filteredData);
    }

    public function first() : ?array
    {
        $this->limit(1);
        $result = $this->get();

        return ! empty($result) ? $result[0] : null;
    }

    public function whereRaw(string $rawCondition, array $bindings = []) : QueryBuilderInterface
    {
        throw new RuntimeException('whereRaw is not supported for file storage');
    }

    private function executeInsert() : void
    {
        $table = $this->query['table'];

        if (! isset($this->data[$table])) {
            $this->data[$table] = [];
        }

        $this->data[$table][] = $this->query['data'];
    }

    private function executeUpdate() : void
    {
        $table = $this->query['table'];

        if (! isset($this->data[$table])) {
            return;
        }

        foreach ($this->data[$table] as &$row) {
            if ($this->evaluateConditionGroup($this->whereConditions, $row)) {
                foreach ($this->query['data'] as $key => $value) {
                    $row[$key] = $value;
                }
            }
        }
    }

    private function executeDelete() : void
    {
        $table = $this->query['table'];

        if (! isset($this->data[$table])) {
            return;
        }

        $this->data[$table] = array_filter($this->data[$table], fn ($row) => ! $this->evaluateConditionGroup($this->whereConditions, $row));

        $this->data[$table] = array_values($this->data[$table]);
    }

    private function evaluateConditionGroup(ConditionGroup $group, array $row) : bool
    {
        $conditions = $group->getConditions();
        if (empty($conditions)) {
            return true;
        }

        $result = null;
        foreach ($conditions as $condition) {
            $currentResult = match ($condition['type']) {
                'condition' => $this->evaluateCondition(
                    $row[$condition['column']] ?? null,
                    $condition['operator'],
                    $condition['value']
                ),
                'group' => $this->evaluateConditionGroup($condition['group'], $row),
                default => false
            };

            if (null === $result) {
                $result = $currentResult;
            } else {
                $result = match ($condition['boolean']) {
                    ConditionGroup::TYPE_AND => $result && $currentResult,
                    ConditionGroup::TYPE_OR => $result || $currentResult,
                };
            }
        }

        return $result;
    }

    private function evaluateCondition($rowValue, string $operator, $value) : bool
    {
        return match ($operator) {
            '=' => $rowValue == $value,
            '!=' => $rowValue != $value,
            '>' => $rowValue > $value,
            '>=' => $rowValue >= $value,
            '<' => $rowValue < $value,
            '<=' => $rowValue <= $value,
            'LIKE' => $this->evaluateLike($rowValue, $value),
            'NOT LIKE' => ! $this->evaluateLike($rowValue, $value),
            'IN' => in_array($rowValue, (array) $value),
            'NOT IN' => ! in_array($rowValue, (array) $value),
            'BETWEEN' => $rowValue >= $value[0] && $rowValue <= $value[1],
            'NOT BETWEEN' => $rowValue < $value[0] || $rowValue > $value[1],
            'EXISTS' => $this->evaluateExists($value),
            'NOT EXISTS' => ! $this->evaluateExists($value),
            default => false
        };
    }

    private function evaluateLike($rowValue, string $pattern) : bool
    {
        if (! is_string($rowValue)) {
            return false;
        }

        $pattern = preg_quote($pattern, '/');

        $pattern = str_replace(
            ['%', '_'],
            ['.*', '.'],
            $pattern
        );

        return (bool) preg_match("/^{$pattern}$/is", $rowValue);
    }

    private function evaluateJoinCondition(array $row1, array $row2, string $column1, string $operator, string $column2) : bool
    {
        $value1 = $row1[$column1] ?? null;
        $value2 = $row2[$column2] ?? null;

        return match ($operator) {
            '=' => $value1 == $value2,
            '!=' => $value1 != $value2,
            '>' => $value1 > $value2,
            '>=' => $value1 >= $value2,
            '<' => $value1 < $value2,
            '<=' => $value1 <= $value2,
            'LIKE' => $this->evaluateLike($value1, (string) $value2),
            default => false
        };
    }

    private function evaluateExists(QueryBuilderInterface $subQuery) : bool
    {
        if ($subQuery instanceof FileQueryBuilder) {
            return ! empty($subQuery->get());
        }

        return false;
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
        if ('select' !== $this->query['type']) {
            throw new RuntimeException('count() can only be called on select queries');
        }

        $filteredData = array_filter($this->data[$this->query['table']] ?? [], fn ($row) => $this->evaluateConditionGroup($this->whereConditions, $row));

        if ('*' === $column) {
            return count($filteredData);
        }

        return count(array_filter($filteredData, fn ($row) => isset($row[$column]) && null !== $row[$column]));
    }

    public function sum(string $column) : float
    {
        if ('select' !== $this->query['type']) {
            throw new RuntimeException('sum() can only be called on select queries');
        }

        $filteredData = array_filter($this->data[$this->query['table']] ?? [], fn ($row) => $this->evaluateConditionGroup($this->whereConditions, $row));

        $sum = 0;
        foreach ($filteredData as $row) {
            if (isset($row[$column])) {
                $sum += (float) $row[$column];
            }
        }

        return $sum;
    }

    public function avg(string $column) : float
    {
        if ('select' !== $this->query['type']) {
            throw new RuntimeException('avg() can only be called on select queries');
        }

        $filteredData = array_filter($this->data[$this->query['table']] ?? [], fn ($row) => $this->evaluateConditionGroup($this->whereConditions, $row));

        $sum = 0;
        $count = 0;
        foreach ($filteredData as $row) {
            if (isset($row[$column])) {
                $sum += (float) $row[$column];
                $count++;
            }
        }

        return $count > 0 ? $sum / $count : 0;
    }

    public function min(string $column) : mixed
    {
        if ('select' !== $this->query['type']) {
            throw new RuntimeException('min() can only be called on select queries');
        }

        $filteredData = array_filter($this->data[$this->query['table']] ?? [], fn ($row) => $this->evaluateConditionGroup($this->whereConditions, $row));

        $min = null;
        foreach ($filteredData as $row) {
            if (isset($row[$column]) && (null === $min || $row[$column] < $min)) {
                $min = $row[$column];
            }
        }

        return $min;
    }

    public function max(string $column) : mixed
    {
        if ('select' !== $this->query['type']) {
            throw new RuntimeException('max() can only be called on select queries');
        }

        $filteredData = array_filter($this->data[$this->query['table']] ?? [], fn ($row) => $this->evaluateConditionGroup($this->whereConditions, $row));

        $max = null;
        foreach ($filteredData as $row) {
            if (isset($row[$column]) && (null === $max || $row[$column] > $max)) {
                $max = $row[$column];
            }
        }

        return $max;
    }

    public function paginate(int $perPage = 15, int $page = 1) : array
    {
        if ('select' !== $this->query['type']) {
            throw new RuntimeException('paginate() can only be called on select queries');
        }

        $page = max(1, $page);
        $total = $this->count();

        $originalLimit = $this->query['limit'];
        $originalOffset = $this->query['offset'];

        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);

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
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($page * $perPage, $total),
            ],
        ];
    }
}
