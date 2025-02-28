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

namespace morfeditorial\builders;

use morfeditorial\interfaces\QueryBuilderInterface;

abstract class BaseQueryBuilder implements QueryBuilderInterface
{
    protected array $query = [
        'type' => null,
        'columns' => '*',
        'table' => null,
        'order' => [],
        'limit' => null,
        'offset' => null,
        'data' => [],
    ];

    protected ConditionGroup $whereConditions;

    protected ?ConditionGroup $currentConditionGroup = null;

    public function __construct()
    {
        $this->whereConditions = new ConditionGroup;
        $this->currentConditionGroup = $this->whereConditions;
        $this->query['joins'] = [];
        $this->query['group'] = [];
    }

    protected function validateData(array $data) : void
    {
        foreach ($data as $value) {
            if (is_object($value) || is_resource($value)) {
                throw new InvalidArgumentException('Invalid data type provided');
            }
        }
    }

    public function select($columns = '*') : QueryBuilderInterface
    {
        $this->query['type'] = 'select';
        $this->query['columns'] = $columns;

        return $this;
    }

    public function from(string $table) : QueryBuilderInterface
    {
        $this->query['table'] = $table;

        return $this;
    }

    public function where(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface
    {
        if (null === $value) {
            $value = $operator;
            $operator = '=';
        }

        $this->currentConditionGroup->addCondition(
            $column,
            $operator,
            $value,
            ConditionGroup::TYPE_AND
        );

        return $this;
    }

    public function andWhere(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface
    {
        return $this->where($column, $operator, $value);
    }

    public function orWhere(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface
    {
        if (null === $value) {
            $value = $operator;
            $operator = '=';
        }

        $this->currentConditionGroup->addCondition(
            $column,
            $operator,
            $value,
            ConditionGroup::TYPE_OR
        );

        return $this;
    }

    public function whereRaw(string $rawCondition, array $bindings = []) : QueryBuilderInterface
    {
        throw new RuntimeException('whereRaw not implemented for this storage type');
    }

    public function whereNested(callable $callback) : QueryBuilderInterface
    {
        $subGroup = new ConditionGroup(ConditionGroup::TYPE_AND);
        $previousGroup = $this->currentConditionGroup;

        $this->currentConditionGroup = $subGroup;
        call_user_func($callback, $this);
        $this->currentConditionGroup = $previousGroup;

        if (! $subGroup->isEmpty()) {
            $this->currentConditionGroup->addGroup($subGroup, ConditionGroup::TYPE_AND);
        }

        return $this;
    }

    public function orWhereNested(callable $callback) : QueryBuilderInterface
    {
        $subGroup = new ConditionGroup(ConditionGroup::TYPE_AND);
        $previousGroup = $this->currentConditionGroup;

        $this->currentConditionGroup = $subGroup;
        call_user_func($callback, $this);
        $this->currentConditionGroup = $previousGroup;

        if (! $subGroup->isEmpty()) {
            $this->currentConditionGroup->addGroup($subGroup, ConditionGroup::TYPE_OR);
        }

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC') : QueryBuilderInterface
    {
        $this->query['order'][] = [
            'column' => $column,
            'direction' => 'DESC' === strtoupper($direction) ? 'DESC' : 'ASC',
        ];

        return $this;
    }

    public function limit(int $limit) : QueryBuilderInterface
    {
        $this->query['limit'] = $limit;

        return $this;
    }

    public function offset(int $offset) : QueryBuilderInterface
    {
        $this->query['offset'] = $offset;

        return $this;
    }

    public function insert(string $table, array $data) : QueryBuilderInterface
    {
        $this->query['type'] = 'insert';
        $this->query['table'] = $table;
        $this->query['data'] = $data;

        return $this;
    }

    public function update(string $table, array $data) : QueryBuilderInterface
    {
        $this->query['type'] = 'update';
        $this->query['table'] = $table;
        $this->query['data'] = $data;

        return $this;
    }

    public function delete(string $table) : QueryBuilderInterface
    {
        $this->query['type'] = 'delete';
        $this->query['table'] = $table;

        return $this;
    }

    public function groupBy(string ...$columns) : QueryBuilderInterface
    {
        if (empty($columns)) {
            throw new InvalidArgumentException('Group by columns cannot be empty');
        }
        $this->query['group'] = array_merge($this->query['group'] ?? [], $columns);

        return $this;
    }

    public function between(string $column, $min, $max) : QueryBuilderInterface
    {
        $this->currentConditionGroup->addCondition(
            $column,
            'BETWEEN',
            [$min, $max],
            ConditionGroup::TYPE_AND
        );

        return $this;
    }

    public function notBetween(string $column, $min, $max) : QueryBuilderInterface
    {
        $this->currentConditionGroup->addCondition(
            $column,
            'NOT BETWEEN',
            [$min, $max],
            ConditionGroup::TYPE_AND
        );

        return $this;
    }

    public function exists(callable $callback) : QueryBuilderInterface
    {
        $subQuery = new static;
        call_user_func($callback, $subQuery);

        $this->currentConditionGroup->addCondition(
            'EXISTS',
            'RAW',
            $subQuery,
            ConditionGroup::TYPE_AND
        );

        return $this;
    }

    public function notExists(callable $callback) : QueryBuilderInterface
    {
        $subQuery = new static;
        call_user_func($callback, $subQuery);

        $this->currentConditionGroup->addCondition(
            'NOT EXISTS',
            'RAW',
            $subQuery,
            ConditionGroup::TYPE_AND
        );

        return $this;
    }

    abstract public function execute() : void;

    abstract public function get() : array;

    abstract public function first() : ?array;
}
