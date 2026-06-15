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

namespace morfeditorial\storage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use morfeditorial\builders\DoctrineQueryBuilder;
use morfeditorial\interfaces\DatabaseConfigInterface;
use morfeditorial\interfaces\QueryBuilderInterface;
use morfeditorial\interfaces\StorageInterface;
use RuntimeException;

class DatabaseStorage implements StorageInterface
{
    private ?Connection $connection = null;

    public function __construct(private DatabaseConfigInterface $config) {}

    public function connect() : void
    {
        if (! $this->connection) {
            try {
                $connectionParams = [
                    'url' => $this->config->getDsn(),
                    'user' => $this->config->getUsername(),
                    'password' => $this->config->getPassword(),
                ];

                // Add PDO options if needed
                $connectionParams['driverOptions'] = $this->config->getOptions();

                $this->connection = DriverManager::getConnection($connectionParams);
            } catch (\Exception $e) {
                throw new RuntimeException('Connection failed: ' . $e->getMessage());
            }
        }
    }

    public function getQueryBuilder() : QueryBuilderInterface
    {
        $this->connect();

        return new DoctrineQueryBuilder($this->connection->createQueryBuilder());
    }

    public function getConnection() : Connection
    {
        $this->connect();

        return $this->connection;
    }

    public function query(string $query, array $params = []) : array
    {
        $this->connect();

        return $this->connection->fetchAllAssociative($query, $params);
    }

    public function execute(string $query, array $params = []) : void
    {
        $this->connect();
        $this->connection->executeStatement($query, $params);
    }

    public function beginTransaction() : void
    {
        $this->connect();
        $this->connection->beginTransaction();
    }

    public function commit() : void
    {
        $this->connect();
        $this->connection->commit();
    }

    public function rollBack() : void
    {
        $this->connect();
        $this->connection->rollBack();
    }

    public function lastInsertId() : string|int
    {
        $this->connect();

        return $this->connection->lastInsertId();
    }
}
