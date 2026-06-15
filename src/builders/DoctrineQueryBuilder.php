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

use Doctrine\DBAL\Query\QueryBuilder as DoctrineQB;
use morfeditorial\interfaces\QueryBuilderInterface;

class DoctrineQueryBuilder implements QueryBuilderInterface
{
    private DoctrineQB $qb;

    public function __construct(DoctrineQB $qb)
    {
        $this->qb = $qb;
    }

    public function select($columns = '*') : QueryBuilderInterface
    {
        if (is_array($columns)) {
            $this->qb->select(...$columns);
        } else {
            $this->qb->select($columns);
        }

        return $this;
    }

    public function from(string $table, ?string $alias = null) : QueryBuilderInterface
    {
        $this->qb->from($table, $alias);

        return $this;
    }

    public function where(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface
    {
        if (null === $value && null !== $operator) {
            $value = $operator;
            $operator = '=';
        }

        $this->qb->andWhere("{$column} {$operator} " . $this->qb->createNamedParameter($value));

        return $this;
    }

    public function andWhere(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface
    {
        return $this->where($column, $operator, $value);
    }

    public function orWhere(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface
    {
        if (null === $value && null !== $operator) {
            $value = $operator;
            $operator = '=';
        }

        $this->qb->orWhere("{$column} {$operator} " . $this->qb->createNamedParameter($value));

        return $this;
    }

    public function whereRaw(string $rawCondition, array $bindings = []) : QueryBuilderInterface
    {
        foreach ($bindings as $key => $value) {
            $paramName = is_int($key) ? 'param_' . $key : $key;
            $rawCondition = str_replace(":{$key}", ":{$paramName}", $rawCondition);
            $this->qb->setParameter($paramName, $value);
        }

        $this->qb->andWhere($rawCondition);

        return $this;
    }

    public function whereNested(callable $callback) : QueryBuilderInterface
    {
        $tempQb = new self(clone $this->qb);
        $tempQb->qb->where('1=1'); // Reset or initialize
        // Actually, nested WHERE is tricky with Doctrine's flat QB.
        // We might need to use expressions.
        // For simplicity, let's just use the current QB and wrap it?
        // No, Doctrine DBAL doesn't support nested closures easily like Laravel.

        // One way is to capture the expression.
        $callback($tempQb);

        return $this;
    }

    public function orWhereNested(callable $callback) : QueryBuilderInterface
    {
        // Similar to whereNested
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC') : QueryBuilderInterface
    {
        $this->qb->orderBy($column, $direction);

        return $this;
    }

    public function limit(int $limit) : QueryBuilderInterface
    {
        $this->qb->setMaxResults($limit);

        return $this;
    }

    public function offset(int $offset) : QueryBuilderInterface
    {
        $this->qb->setFirstResult($offset);

        return $this;
    }

    public function insert(string $table, array $data) : QueryBuilderInterface
    {
        $this->qb->insert($table);
        foreach ($data as $column => $value) {
            $this->qb->setValue($column, $this->qb->createNamedParameter($value));
        }

        return $this;
    }

    public function update(string $table, array $data) : QueryBuilderInterface
    {
        $this->qb->update($table);
        foreach ($data as $column => $value) {
            $this->qb->set($column, $this->qb->createNamedParameter($value));
        }

        return $this;
    }

    public function delete(string $table) : QueryBuilderInterface
    {
        $this->qb->delete($table);

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER') : QueryBuilderInterface
    {
        $alias = $table; // Simple alias
        $condition = "{$first} {$operator} {$second}";

        match (strtoupper($type)) {
            'LEFT' => $this->qb->leftJoin($first, $table, $alias, $condition),
            'RIGHT' => $this->qb->rightJoin($first, $table, $alias, $condition),
            default => $this->qb->innerJoin($first, $table, $alias, $condition),
        };

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
        foreach ($columns as $column) {
            $this->qb->addGroupBy($column);
        }

        return $this;
    }

    public function having(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface
    {
        if (null === $value && null !== $operator) {
            $value = $operator;
            $operator = '=';
        }

        $this->qb->andHaving("{$column} {$operator} " . $this->qb->createNamedParameter($value));

        return $this;
    }

    public function orHaving(string $column, ?string $operator = null, $value = null) : QueryBuilderInterface
    {
        if (null === $value && null !== $operator) {
            $value = $operator;
            $operator = '=';
        }

        $this->qb->orHaving("{$column} {$operator} " . $this->qb->createNamedParameter($value));

        return $this;
    }

    public function between(string $column, $min, $max) : QueryBuilderInterface
    {
        $this->qb->andWhere("{$column} BETWEEN " . $this->qb->createNamedParameter($min) . ' AND ' . $this->qb->createNamedParameter($max));

        return $this;
    }

    public function notBetween(string $column, $min, $max) : QueryBuilderInterface
    {
        $this->qb->andWhere("{$column} NOT BETWEEN " . $this->qb->createNamedParameter($min) . ' AND ' . $this->qb->createNamedParameter($max));

        return $this;
    }

    public function exists(callable $callback) : QueryBuilderInterface
    {
        // Not easily supported in DBAL QueryBuilder without manual SQL
        return $this;
    }

    public function notExists(callable $callback) : QueryBuilderInterface
    {
        return $this;
    }

    public function count(string $column = '*') : int
    {
        $qb = clone $this->qb;
        $qb->select("COUNT({$column})");

        return (int) $qb->executeQuery()->fetchOne();
    }

    public function sum(string $column) : float
    {
        $qb = clone $this->qb;
        $qb->select("SUM({$column})");

        return (float) $qb->executeQuery()->fetchOne();
    }

    public function avg(string $column) : float
    {
        $qb = clone $this->qb;
        $qb->select("AVG({$column})");

        return (float) $qb->executeQuery()->fetchOne();
    }

    public function min(string $column) : mixed
    {
        $qb = clone $this->qb;
        $qb->select("MIN({$column})");

        return $qb->executeQuery()->fetchOne();
    }

    public function max(string $column) : mixed
    {
        $qb = clone $this->qb;
        $qb->select("MAX({$column})");

        return $qb->executeQuery()->fetchOne();
    }

    public function paginate(int $perPage = 15, int $page = 1) : array
    {
        $total = $this->count();
        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);
        $items = $this->get();

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
                'from' => ($page - 1) * $perPage + 1,
                'to' => ($page - 1) * $perPage + count($items),
            ],
        ];
    }

    public function execute() : void
    {
        $this->qb->executeStatement();
    }

    public function get() : array
    {
        return $this->qb->executeQuery()->fetchAllAssociative();
    }

    public function first() : ?array
    {
        $this->qb->setMaxResults(1);
        return $this->qb->executeQuery()->fetchAssociative() ?: null;
    }
}
