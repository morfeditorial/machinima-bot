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
 * Copyright (c) 2024 Serhii Cherneha
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
use morfeditorial\config\DatabaseConfigInterface;
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
                $this->initializeSchema();
            } catch (\Exception $e) {
                throw new RuntimeException('Connection failed: ' . $e->getMessage());
            }
        }
    }

    private function initializeSchema() : void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (! $schemaManager->tablesExist(['authors'])) {
            $this->connection->executeStatement("
                CREATE TABLE authors (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    biography TEXT DEFAULT NULL,
                    channel_link TEXT DEFAULT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    state TEXT
                )
            ");

            $this->connection->executeStatement("
                CREATE TABLE content (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    author_id INTEGER,
                    description TEXT,
                    tags TEXT,
                    FOREIGN KEY (author_id) REFERENCES authors(id)
                )
            ");

            $this->connection->executeStatement("
                CREATE TABLE user_data (
                    user_id INTEGER PRIMARY KEY,
                    user_state TEXT,
                    current_panel INTEGER,
                    current_page TEXT,
                    role TEXT
                )
            ");

            $this->connection->executeStatement("
                CREATE TABLE user_states (
                    user_id INTEGER,
                    state_key TEXT,
                    state_value TEXT,
                    PRIMARY KEY (user_id, state_key)
                )
            ");

            $this->connection->executeStatement("
                CREATE TABLE roles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    role_name TEXT NOT NULL UNIQUE,
                    priority INTEGER NOT NULL
                )
            ");

            $this->connection->executeStatement("
                CREATE TABLE user_roles (
                    user_id INTEGER NOT NULL,
                    role_id INTEGER NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES user_data(user_id),
                    FOREIGN KEY (role_id) REFERENCES roles(id),
                    PRIMARY KEY (user_id)
                )
            ");
        }
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
