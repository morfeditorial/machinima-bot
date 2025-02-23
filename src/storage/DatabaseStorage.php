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
 */

declare(strict_types=1);

namespace morfeditorial\storage;

use morfeditorial\interfaces\DatabaseConfigInterface;
use morfeditorial\interfaces\StorageInterface;

class DatabaseStorage implements StorageInterface
{
    private ?PDO $connection = null;

    public function __construct(private DatabaseConfigInterface $config) {}

    public function connect() : void
    {
        if (! $this->connection) {
            try {
                $this->connection = new PDO(
                    $this->config->getDsn(),
                    $this->config->getUsername(),
                    $this->config->getPassword(),
                    $this->config->getOptions()
                );
            } catch (PDOException $e) {
                throw new RuntimeException('Connection failed: ' . $e->getMessage());
            }
        }
    }

    public function query(string $query, array $params = []) : array
    {
        $this->connect();
        $stmt = $this->prepareStatement($query, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function execute(string $query, array $params = []) : void
    {
        $this->connect();
        $stmt = $this->prepareStatement($query, $params);
        $stmt->execute();
    }

    private function prepareStatement(string $query, array $params) : PDOStatement
    {
        $stmt = $this->connection->prepare($query);
        foreach ($params as $key => $value) {
            $type = match (gettype($value)) {
                'integer' => PDO::PARAM_INT,
                'boolean' => PDO::PARAM_BOOL,
                'NULL' => PDO::PARAM_NULL,
                default => PDO::PARAM_STR
            };
            $paramName = is_int($key) ? $key + 1 : ":{$key}";
            $stmt->bindValue($paramName, $value, $type);
        }

        return $stmt;
    }
}
