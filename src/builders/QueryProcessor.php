<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \\/       \\//       \//       \
 *  /        //        ///        //      __/
 * /         /         /        _/        _/
 * \__/__/__/\________/\____/___/\_______/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author CSSM Group
 * @link https://cssm.pp.ua/
 *
 *
 */

declare(strict_types=1);

namespace morfeditorial\processor;

class QueryProcessor
{
    private array $schema = [];

    private array $constraints = [];

    public function processQuery(array $data, string $query, array $params = []) : array
    {
        $query = preg_replace('/\s+/', ' ', trim($query));

        if (preg_match('/^SELECT/i', $query)) {
            if (preg_match('/(UNION|UNION ALL|INTERSECT|EXCEPT)/i', $query)) {
                return $this->handleSetOperation($data, $query, $params);
            }

            return $this->handleSelect($data, $query, $params);
        }
        if (preg_match('/^SELECT COUNT/i', $query)) {
            return $this->handleCount($data, $query, $params);
        }
        if (preg_match('/^SELECT (AVG|SUM|MIN|MAX)/i', $query)) {
            return $this->handleAggregate($data, $query, $params);
        }

        return [];
    }

    public function processExecution(array $data, string $query, array $params = []) : array
    {
        $query = preg_replace('/\s+/', ' ', trim($query));

        if (preg_match('/^INSERT/i', $query)) {
            return $this->handleInsert($data, $query, $params);
        }
        if (preg_match('/^UPDATE/i', $query)) {
            return $this->handleUpdate($data, $query, $params);
        }
        if (preg_match('/^DELETE/i', $query)) {
            return $this->handleDelete($data, $query, $params);
        }
        if (preg_match('/^CREATE TABLE/i', $query)) {
            return $this->handleCreateTable($data, $query);
        }
        if (preg_match('/^ALTER TABLE/i', $query)) {
            return $this->handleAlterTable($data, $query);
        }
        if (preg_match('/^DROP TABLE/i', $query)) {
            return $this->handleDropTable($data, $query);
        }

        return $data;
    }

    private function handleSelect(array $data, string $query, array $params) : array
    {
        preg_match('/SELECT\s+(.*?)\s+FROM\s+(\w+)(?:\s+(?:(INNER|LEFT|RIGHT|FULL)\s+JOIN\s+(\w+)\s+ON\s+(.*?))*)?(?:\s+WHERE\s+(.*?))?(?:\s+GROUP BY\s+(.*?))?(?:\s+HAVING\s+(.*?))?(?:\s+ORDER BY\s+(.*?)(?:\s+(ASC|DESC))?)?(?:\s+LIMIT\s+(\d+)(?:\s*,\s*(\d+))?)?/is',
            $query,
            $matches
        );

        $columns = array_map('trim', explode(',', $matches[1]));
        $mainTable = $matches[2];
        $joins = $this->parseJoins($query);
        $whereClause = $matches[6] ?? null;
        $groupBy = $matches[7] ?? null;
        $having = $matches[8] ?? null;
        $orderBy = $matches[9] ?? null;
        $orderDir = $matches[10] ?? 'ASC';
        $limit = $matches[11] ?? null;
        $offset = $matches[12] ?? 0;

        if (! isset($data[$mainTable])) {
            return [];
        }

        $result = $data[$mainTable];

        if (! empty($joins)) {
            $result = $this->applyJoins($result, $data, $joins);
        }

        if ($whereClause) {
            $whereClause = $this->processSubqueries($whereClause, $data, $params);
            $result = $this->applyWhere($result, $whereClause, $params);
        }

        if ($groupBy) {
            $result = $this->applyGroupBy($result, $groupBy, $columns);
        }

        if ($having) {
            $result = $this->applyHaving($result, $having, $params);
        }

        if ($orderBy) {
            $orderByColumns = array_map('trim', explode(',', $orderBy));
            usort($result, function ($a, $b) use ($orderByColumns, $orderDir) {
                foreach ($orderByColumns as $column) {
                    $comparison = $a[$column] <=> $b[$column];
                    if (0 !== $comparison) {
                        return 'DESC' === $orderDir ? -$comparison : $comparison;
                    }
                }

                return 0;
            });
        }

        if (null !== $limit) {
            $result = array_slice($result, (int) $offset, (int) $limit);
        }

        if ('*' !== $columns[0]) {
            $result = array_map(fn ($row) => array_intersect_key($row, array_flip($columns)), $result);
        }

        return $result;
    }

    private function parseJoins(string $query) : array
    {
        $joins = [];
        preg_match_all('/(INNER|LEFT|RIGHT|FULL)\s+JOIN\s+(\w+)\s+ON\s+(.*?)(?=\s+(?:INNER|LEFT|RIGHT|FULL)\s+JOIN|\s+WHERE|\s+GROUP BY|\s+ORDER BY|\s+LIMIT|$)/is',
            $query,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $joins[] = [
                'type' => strtoupper($match[1]),
                'table' => $match[2],
                'condition' => $match[3],
            ];
        }

        return $joins;
    }

    private function applyJoins(array $leftTable, array $data, array $joins) : array
    {
        $result = $leftTable;

        foreach ($joins as $join) {
            $rightTable = $data[$join['table']] ?? [];
            $joinCondition = $join['condition'];
            $joinType = $join['type'];

            $temp = [];
            foreach ($result as $leftRow) {
                $matched = false;
                foreach ($rightTable as $rightRow) {
                    if ($this->evaluateJoinCondition($leftRow, $rightRow, $joinCondition)) {
                        $temp[] = array_merge($leftRow, array_combine(
                            array_map(fn ($key) => "{$join['table']}_{$key}", array_keys($rightRow)),
                            array_values($rightRow)
                        ));
                        $matched = true;
                    }
                }

                if (! $matched && in_array($joinType, ['LEFT', 'FULL'])) {
                    $temp[] = array_merge($leftRow, array_combine(
                        array_map(fn ($key) => "{$join['table']}_{$key}", array_keys(reset($rightTable))),
                        array_fill(0, count(reset($rightTable)), null)
                    ));
                }
            }

            if (in_array($joinType, ['RIGHT', 'FULL'])) {
                foreach ($rightTable as $rightRow) {
                    $matched = false;
                    foreach ($result as $leftRow) {
                        if ($this->evaluateJoinCondition($leftRow, $rightRow, $joinCondition)) {
                            $matched = true;
                            break;
                        }
                    }

                    if (! $matched) {
                        $temp[] = array_merge(
                            array_fill_keys(array_keys(reset($result)), null),
                            array_combine(
                                array_map(fn ($key) => "{$join['table']}_{$key}", array_keys($rightRow)),
                                array_values($rightRow)
                            )
                        );
                    }
                }
            }

            $result = $temp;
        }

        return $result;
    }

    private function evaluateJoinCondition(array $leftRow, array $rightRow, string $condition) : bool
    {
        // Парсимо умову JOIN (спрощений варіант)
        preg_match('/(\w+)\s*=\s*(\w+)/', $condition, $matches);
        $leftColumn = $matches[1];
        $rightColumn = $matches[2];

        return $leftRow[$leftColumn] === $rightRow[$rightColumn];
    }

    private function handleCount(array $data, string $query, array $params) : array
    {
        preg_match('/SELECT COUNT\((.*?)\)\s+FROM\s+(\w+)(?:\s+WHERE\s+(.*?))?/i', $query, $matches);

        $column = $matches[1];
        $table = $matches[2];
        $whereClause = $matches[3] ?? null;

        $result = $data[$table] ?? [];

        if ($whereClause) {
            $result = $this->applyWhere($result, $whereClause, $params);
        }

        return [['count' => count($result)]];
    }

    private function handleAggregate(array $data, string $query, array $params) : array
    {
        preg_match('/SELECT (AVG|SUM|MIN|MAX)\((.*?)\)\s+FROM\s+(\w+)(?:\s+WHERE\s+(.*?))?/i', $query, $matches);

        $function = strtoupper($matches[1]);
        $column = $matches[2];
        $table = $matches[3];
        $whereClause = $matches[4] ?? null;

        $result = $data[$table] ?? [];

        if ($whereClause) {
            $result = $this->applyWhere($result, $whereClause, $params);
        }

        $values = array_column($result, $column);

        switch ($function) {
            case 'AVG':
                $value = ! empty($values) ? array_sum($values) / count($values) : 0;
                break;
            case 'SUM':
                $value = array_sum($values);
                break;
            case 'MIN':
                $value = ! empty($values) ? min($values) : null;
                break;
            case 'MAX':
                $value = ! empty($values) ? max($values) : null;
                break;
            default:
                $value = null;
        }

        return [[strtolower($function) => $value]];
    }

    private function handleInsert(array $data, string $query, array $params) : array
    {
        preg_match('/INSERT INTO (\w+)\s+\((.*?)\)\s+VALUES\s+\((.*?)\)/i', $query, $matches);
        $table = $matches[1];
        $columns = array_map('trim', explode(',', $matches[2]));
        $values = array_map('trim', explode(',', $matches[3]));

        $newRow = [];
        foreach ($columns as $index => $column) {
            $value = trim($values[$index], ':');
            $newRow[$column] = $params[$value] ?? null;
        }

        if (! isset($data[$table])) {
            $data[$table] = [];
        }

        $data[$table][] = $newRow;

        return $data;
    }

    private function handleUpdate(array $data, string $query, array $params) : array
    {
        preg_match('/UPDATE (\w+)\s+SET (.*?)(?:\s+WHERE\s+(.*?))?/i', $query, $matches);
        $table = $matches[1];
        $setClause = $matches[2];
        $whereClause = $matches[3] ?? null;

        if (! isset($data[$table])) {
            return $data;
        }

        $setParts = explode(',', $setClause);
        $updates = [];
        foreach ($setParts as $part) {
            preg_match('/(\w+)\s*=\s*:(\w+)/', trim($part), $setMatch);
            $updates[$setMatch[1]] = $params[$setMatch[2]] ?? null;
        }

        $result = $data[$table];
        if ($whereClause) {
            $result = $this->applyWhere($result, $whereClause, $params);
        }

        foreach ($result as &$row) {
            foreach ($updates as $column => $value) {
                $row[$column] = $value;
            }
        }

        $data[$table] = $result;

        return $data;
    }

    private function handleDelete(array $data, string $query, array $params) : array
    {
        preg_match('/DELETE FROM (\w+)(?:\s+WHERE\s+(.*?))?/i', $query, $matches);
        $table = $matches[1];
        $whereClause = $matches[2] ?? null;

        if (! isset($data[$table])) {
            return $data;
        }

        $result = $data[$table];
        if ($whereClause) {
            $result = $this->applyWhere($result, $whereClause, $params);
        }

        $data[$table] = array_udiff($data[$table], $result, fn ($a, $b) => $a <=> $b);

        return $data;
    }

    private function handleCreateTable(array $data, string $query) : array
    {
        preg_match('/CREATE TABLE (\w+)\s*\((.*?)\)/is', $query, $matches);
        $table = $matches[1];
        $columnDefs = $matches[2];

        $columns = [];
        $constraints = [];

        foreach (array_map('trim', explode(',', $columnDefs)) as $def) {
            if (preg_match('/^CONSTRAINT\s+(\w+)\s+(.+)$/i', $def, $constraintMatch)) {
                $constraints[$constraintMatch[1]] = $this->parseConstraint($constraintMatch[2]);
            } else {
                $columnDef = $this->parseColumnDefinition($def);
                $columns[$columnDef['name']] = $columnDef;
            }
        }

        $this->schema[$table] = $columns;
        $this->constraints[$table] = $constraints;
        $data[$table] = [];

        return $data;
    }

    private function parseColumnDefinition(string $def) : array
    {
        preg_match('/(\w+)\s+(\w+)(?:\((\d+)(?:,\s*(\d+))?\))?(?:\s+(.+))?/i', $def, $matches);

        return [
            'name' => $matches[1],
            'type' => strtoupper($matches[2]),
            'length' => $matches[3] ?? null,
            'decimals' => $matches[4] ?? null,
            'constraints' => $this->parseInlineConstraints($matches[5] ?? ''),
        ];
    }

    private function parseInlineConstraints(string $constraints) : array
    {
        $result = [];

        if (preg_match('/PRIMARY KEY/i', $constraints)) {
            $result['primary_key'] = true;
        }
        if (preg_match('/UNIQUE/i', $constraints)) {
            $result['unique'] = true;
        }
        if (preg_match('/NOT NULL/i', $constraints)) {
            $result['not_null'] = true;
        }
        if (preg_match('/DEFAULT\s+(.+?)(?:\s|$)/i', $constraints, $matches)) {
            $result['default'] = trim($matches[1]);
        }

        return $result;
    }

    private function parseConstraint(string $constraint) : array
    {
        if (preg_match('/PRIMARY KEY\s*\((.*?)\)/i', $constraint, $matches)) {
            return [
                'type' => 'PRIMARY KEY',
                'columns' => array_map('trim', explode(',', $matches[1])),
            ];
        }

        if (preg_match('/FOREIGN KEY\s*\((.*?)\)\s*REFERENCES\s+(\w+)\s*\((.*?)\)/i', $constraint, $matches)) {
            return [
                'type' => 'FOREIGN KEY',
                'columns' => array_map('trim', explode(',', $matches[1])),
                'references' => [
                    'table' => $matches[2],
                    'columns' => array_map('trim', explode(',', $matches[3])),
                ],
            ];
        }

        if (preg_match('/UNIQUE\s*\((.*?)\)/i', $constraint, $matches)) {
            return [
                'type' => 'UNIQUE',
                'columns' => array_map('trim', explode(',', $matches[1])),
            ];
        }

        if (preg_match('/CHECK\s*\((.*?)\)/i', $constraint, $matches)) {
            return [
                'type' => 'CHECK',
                'expression' => $matches[1],
            ];
        }

        return [];
    }

    private function handleAlterTable(array $data, string $query) : array
    {
        preg_match('/ALTER TABLE (\w+)\s+(.*)/i', $query, $matches);
        $table = $matches[1];
        $operation = $matches[2];

        if (preg_match('/ADD\s+COLUMN\s+(.+)/i', $operation, $addMatch)) {
            $columnDef = $this->parseColumnDefinition($addMatch[1]);
            $this->schema[$table][$columnDef['name']] = $columnDef;

            foreach ($data[$table] as &$row) {
                $row[$columnDef['name']] = null;
            }
        } elseif (preg_match('/DROP\s+COLUMN\s+(\w+)/i', $operation, $dropMatch)) {
            $columnName = $dropMatch[1];
            unset($this->schema[$table][$columnName]);

            foreach ($data[$table] as &$row) {
                unset($row[$columnName]);
            }
        } elseif (preg_match('/ADD\s+CONSTRAINT\s+(\w+)\s+(.+)/i', $operation, $constraintMatch)) {
            $constraintName = $constraintMatch[1];
            $this->constraints[$table][$constraintName] = $this->parseConstraint($constraintMatch[2]);
        } elseif (preg_match('/DROP\s+CONSTRAINT\s+(\w+)/i', $operation, $dropConstraintMatch)) {
            $constraintName = $dropConstraintMatch[1];
            unset($this->constraints[$table][$constraintName]);
        } elseif (preg_match('/MODIFY\s+COLUMN\s+(.+)/i', $operation, $modifyMatch)) {
            $columnDef = $this->parseColumnDefinition($modifyMatch[1]);
            $this->schema[$table][$columnDef['name']] = $columnDef;
        }

        return $data;
    }

    private function handleDropTable(array $data, string $query) : array
    {
        preg_match('/DROP TABLE (\w+)/i', $query, $matches);
        $table = $matches[1];

        unset($data[$table]);

        return $data;
    }

    private function handleSetOperation(array $data, string $query, array $params) : array
    {
        $parts = preg_split('/(UNION|UNION ALL|INTERSECT|EXCEPT)/i', $query, -1, PREG_SPLIT_DELIM_CAPTURE);
        $results = [];
        $operation = null;

        for ($i = 0; $i < count($parts); $i += 2) {
            $subquery = trim($parts[$i]);
            $currentResult = $this->handleSelect($data, $subquery, $params);

            if (0 === $i) {
                $results = $currentResult;
            } else {
                switch (strtoupper(trim($parts[$i - 1]))) {
                    case 'UNION':
                        $results = array_unique(array_merge($results, $currentResult), SORT_REGULAR);
                        break;
                    case 'UNION ALL':
                        $results = array_merge($results, $currentResult);
                        break;
                    case 'INTERSECT':
                        $results = array_intersect($results, $currentResult);
                        break;
                    case 'EXCEPT':
                        $results = array_diff($results, $currentResult);
                        break;
                }
            }
        }

        return $results;
    }

    private function processSubqueries(string $clause, array $data, array $params) : string
    {
        $clause = preg_replace_callback('/EXISTS\s*\((.*?)\)/is',
            function ($matches) use ($data, $params) {
                $subquery = $this->handleSelect($data, $matches[1], $params);

                return ! empty($subquery) ? 'TRUE' : 'FALSE';
            },
            $clause
        );

        $clause = preg_replace_callback('/(\w+)\s*(=|!=|>|<|>=|<=)\s*ANY\s*\((.*?)\)/is',
            function ($matches) use ($data, $params) {
                $column = $matches[1];
                $operator = $matches[2];
                $subquery = $this->handleSelect($data, $matches[3], $params);
                $values = array_column($subquery, $column);

                switch ($operator) {
                    case '=':
                        return "($column IN (" . implode(',', $values) . '))';
                    case '!=':
                        return "($column NOT IN (" . implode(',', $values) . '))';
                    case '>':
                        return "($column > " . min($values) . ')';
                    case '<':
                        return "($column < " . max($values) . ')';
                    case '>=':
                        return "($column >= " . min($values) . ')';
                    case '<=':
                        return "($column <= " . max($values) . ')';
                }

                return 'FALSE';
            },
            $clause
        );

        $clause = preg_replace_callback('/(\w+)\s*(=|!=|>|<|>=|<=)\s*ALL\s*\((.*?)\)/is',
            function ($matches) use ($data, $params) {
                $column = $matches[1];
                $operator = $matches[2];
                $subquery = $this->handleSelect($data, $matches[3], $params);
                $values = array_column($subquery, $column);

                switch ($operator) {
                    case '=':
                        return 1 === count(array_unique($values)) ?
                               "($column = " . reset($values) . ')' : 'FALSE';
                    case '!=':
                        return "($column NOT IN (" . implode(',', $values) . '))';
                    case '>':
                        return "($column > " . max($values) . ')';
                    case '<':
                        return "($column < " . min($values) . ')';
                    case '>=':
                        return "($column >= " . max($values) . ')';
                    case '<=':
                        return "($column <= " . min($values) . ')';
                }

                return 'FALSE';
            },
            $clause
        );

        $clause = preg_replace_callback('/(\w+)\s+(NOT\s+)?IN\s*\((SELECT.*?)\)/is',
            function ($matches) use ($data, $params) {
                $column = $matches[1];
                $notOperator = ! empty($matches[2]);
                $subquery = $this->handleSelect($data, $matches[3], $params);
                $values = array_unique(array_column($subquery, array_key_first(reset($subquery))));

                return sprintf(
                    '(%s %sIN (%s))',
                    $column,
                    $notOperator ? 'NOT ' : '',
                    implode(',', $values)
                );
            },
            $clause
        );

        $clause = preg_replace_callback('/\((SELECT.*?)\)/is',
            function ($matches) use ($data, $params) {
                $subquery = $this->handleSelect($data, $matches[1], $params);
                if (empty($subquery)) {
                    return 'NULL';
                }
                $firstRow = reset($subquery);

                return (string) reset($firstRow);
            },
            $clause
        );

        return $clause;
    }

    private function applyWhere(array $rows, string $whereClause, array $params) : array
    {
        return array_filter($rows, function ($row) use ($whereClause, $params) {
            $conditions = $this->parseWhereConditions($whereClause);

            return $this->evaluateConditions($row, $conditions, $params);
        });
    }

    private function parseWhereConditions(string $whereClause) : array
    {
        $conditions = [];

        $whereClause = preg_replace_callback('/(.*?)/', fn ($matches) => '(' . implode(' ', $this->parseWhereConditions($matches[1])) . ')', $whereClause);

        $parts = preg_split('/\s+(AND|OR)\s+/i', $whereClause, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 0; $i < count($parts); $i++) {
            $condition = trim($parts[$i]);

            if (in_array(strtoupper($condition), ['AND', 'OR'])) {
                continue;
            }

            if (preg_match('/(\w+)\s*(=|!=|>|<|>=|<=|LIKE|IN)\s*(:?\w+)/', $condition, $matches)) {
                $conditions[] = [
                    'column' => $matches[1],
                    'operator' => $matches[2],
                    'value' => $matches[3],
                    'connector' => $parts[$i + 1] ?? null,
                ];
            }
        }

        return $conditions;
    }

    private function evaluateConditions(array $row, array $conditions, array $params) : bool
    {
        $result = true;
        $lastConnector = null;

        foreach ($conditions as $condition) {
            $column = $condition['column'];
            $operator = $condition['operator'];
            $value = trim($condition['value'], ':');
            $paramValue = $params[$value] ?? null;

            $evaluation = $this->evaluateCondition(
                $row[$column] ?? null,
                $operator,
                $paramValue
            );

            if ('OR' === $lastConnector) {
                $result = $result || $evaluation;
            } elseif ('AND' === $lastConnector) {
                $result = $result && $evaluation;
            } else {
                $result = $evaluation;
            }

            $lastConnector = $condition['connector'];
        }

        return $result;
    }

    private function evaluateCondition($fieldValue, string $operator, $compareValue) : bool
    {
        switch (strtoupper($operator)) {
            case '=':
                return $fieldValue == $compareValue;
            case '!=':
                return $fieldValue != $compareValue;
            case '>':
                return $fieldValue > $compareValue;
            case '<':
                return $fieldValue < $compareValue;
            case '>=':
                return $fieldValue >= $compareValue;
            case '<=':
                return $fieldValue <= $compareValue;
            case 'LIKE':
                $pattern = str_replace('%', '.*', preg_quote($compareValue));

                return (bool) preg_match("/^{$pattern}$/i", (string) $fieldValue);
            case 'IN':
                return in_array($fieldValue, (array) $compareValue, true);
            default:
                return false;
        }
    }

    private function applyGroupBy(array $rows, string $groupBy, array $columns) : array
    {
        $groupColumns = array_map('trim', explode(',', $groupBy));
        $groups = [];

        foreach ($rows as $row) {
            $groupKey = implode('|', array_map(fn ($col) => $row[$col], $groupColumns));
            $groups[$groupKey][] = $row;
        }

        $result = [];
        foreach ($groups as $group) {
            $aggregatedRow = [];
            foreach ($columns as $column) {
                if (preg_match('/(COUNT|SUM|AVG|MIN|MAX)\((.*?)\)/', $column, $matches)) {
                    $func = strtoupper($matches[1]);
                    $col = $matches[2];
                    switch ($func) {
                        case 'COUNT':
                            $aggregatedRow[$column] = count($group);
                            break;
                        case 'SUM':
                            $aggregatedRow[$column] = array_sum(array_column($group, $col));
                            break;
                        case 'AVG':
                            $aggregatedRow[$column] = ! empty($group) ? array_sum(array_column($group, $col)) / count($group) : 0;
                            break;
                        case 'MIN':
                            $aggregatedRow[$column] = ! empty($group) ? min(array_column($group, $col)) : null;
                            break;
                        case 'MAX':
                            $aggregatedRow[$column] = ! empty($group) ? max(array_column($group, $col)) : null;
                            break;
                    }
                } else {
                    $aggregatedRow[$column] = $group[0][$column] ?? null;
                }
            }
            $result[] = $aggregatedRow;
        }

        return $result;
    }

    private function applyHaving(array $rows, string $havingClause, array $params) : array
    {
        $conditions = $this->parseWhereConditions($havingClause);

        return array_filter($rows, fn ($row) => $this->evaluateConditions($row, $conditions, $params));
    }
}
