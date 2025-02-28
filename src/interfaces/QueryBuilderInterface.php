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

namespace morfeditorial\interfaces;

interface QueryBuilderInterface
{
    public function select($columns = '*') : QueryBuilderInterface;

    public function from(string $table) : QueryBuilderInterface;

    public function where(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface;

    public function andWhere(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface;

    public function orWhere(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface;

    public function whereRaw(string $rawCondition, array $bindings = []) : QueryBuilderInterface;

    public function whereNested(callable $callback) : QueryBuilderInterface;

    public function orWhereNested(callable $callback) : QueryBuilderInterface;

    public function orderBy(string $column, string $direction = 'ASC') : QueryBuilderInterface;

    public function limit(int $limit) : QueryBuilderInterface;

    public function offset(int $offset) : QueryBuilderInterface;

    public function insert(string $table, array $data) : QueryBuilderInterface;

    public function update(string $table, array $data) : QueryBuilderInterface;

    public function delete(string $table) : QueryBuilderInterface;

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER') : QueryBuilderInterface;

    public function leftJoin(string $table, string $first, string $operator, string $second) : QueryBuilderInterface;

    public function rightJoin(string $table, string $first, string $operator, string $second) : QueryBuilderInterface;

    public function groupBy(string ...$columns) : QueryBuilderInterface;

    public function having(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface;

    public function orHaving(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface;

    public function between(string $column, $min, $max) : QueryBuilderInterface;

    public function notBetween(string $column, $min, $max) : QueryBuilderInterface;

    public function exists(callable $callback) : QueryBuilderInterface;

    public function notExists(callable $callback) : QueryBuilderInterface;

    public function count(string $column = '*') : int;

    public function sum(string $column) : float;

    public function avg(string $column) : float;

    public function min(string $column) : mixed;

    public function max(string $column) : mixed;

    public function paginate(int $perPage = 15, int $page = 1) : array;

    public function execute() : void;

    public function get() : array;

    public function first() : ?array;
}
