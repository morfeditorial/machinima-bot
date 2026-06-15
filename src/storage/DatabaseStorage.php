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
use RuntimeException;

class DatabaseStorage implements StorageInterface
{
    private ?Connection $connection = null;

    public function __construct(private array $connection_params) {}

    public function connect() : void
    {
        if (! $this->connection) {
            try {
                $connection_params = $this->connection_params;
                $connection_params['driverOptions'] = [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ];

                $this->connection = DriverManager::getConnection($connection_params);
                $this->initializeSchema();
            } catch (\Exception $e) {
                throw new RuntimeException('Connection failed: ' . $e->getMessage());
            }
        }
    }

    private function initializeSchema() : void
    {
        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS authors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                biography TEXT DEFAULT NULL,
                channel_link TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                state TEXT
            )
        ");

        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                parent_id INTEGER,
                FOREIGN KEY (parent_id) REFERENCES categories(id)
            )
        ");

        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS content (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                type TEXT NOT NULL,
                description TEXT,
                url TEXT,
                release_date TEXT,
                status TEXT NOT NULL,
                cover_file_id TEXT,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS content_categories (
                content_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                PRIMARY KEY (content_id, category_id),
                FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            )
        ");

        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS content_staff (
                content_id INTEGER NOT NULL,
                author_id INTEGER NOT NULL,
                role TEXT NOT NULL,
                PRIMARY KEY (content_id, author_id, role),
                FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
                FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE
            )
        ");

        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS user_data (
                user_id INTEGER PRIMARY KEY,
                user_state TEXT,
                current_panel INTEGER,
                current_page TEXT,
                role TEXT
            )
        ");

        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS user_states (
                user_id INTEGER,
                state_key TEXT,
                state_value TEXT,
                PRIMARY KEY (user_id, state_key)
            )
        ");

        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_name TEXT NOT NULL UNIQUE
            )
        ");

        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS role_hierarchy (
                parent_role_id INTEGER NOT NULL,
                child_role_id INTEGER NOT NULL,
                PRIMARY KEY (parent_role_id, child_role_id),
                FOREIGN KEY (parent_role_id) REFERENCES roles(id),
                FOREIGN KEY (child_role_id) REFERENCES roles(id)
            )
        ");

        $this->connection->executeStatement("
            CREATE TABLE IF NOT EXISTS user_roles (
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                FOREIGN KEY (user_id) REFERENCES user_data(user_id),
                FOREIGN KEY (role_id) REFERENCES roles(id),
                PRIMARY KEY (user_id, role_id)
            )
        ");

        $this->connection->executeStatement(
            'INSERT OR IGNORE INTO roles (role_name) VALUES (?)',
            ['admin']
        );
        $this->connection->executeStatement(
            'INSERT OR IGNORE INTO roles (role_name) VALUES (?)',
            ['moderator']
        );
        $this->connection->executeStatement(
            'INSERT OR IGNORE INTO roles (role_name) VALUES (?)',
            ['user']
        );

        $admin_id = $this->connection->fetchOne(
            'SELECT id FROM roles WHERE role_name = ?',
            ['admin']
        );
        $moderator_id = $this->connection->fetchOne(
            'SELECT id FROM roles WHERE role_name = ?',
            ['moderator']
        );
        $user_id = $this->connection->fetchOne(
            'SELECT id FROM roles WHERE role_name = ?',
            ['user']
        );

        $this->connection->executeStatement(
            'INSERT OR IGNORE INTO role_hierarchy (parent_role_id, child_role_id) VALUES (?, ?)',
            [$admin_id, $moderator_id]
        );
        $this->connection->executeStatement(
            'INSERT OR IGNORE INTO role_hierarchy (parent_role_id, child_role_id) VALUES (?, ?)',
            [$admin_id, $user_id]
        );
        $this->connection->executeStatement(
            'INSERT OR IGNORE INTO role_hierarchy (parent_role_id, child_role_id) VALUES (?, ?)',
            [$moderator_id, $user_id]
        );
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
