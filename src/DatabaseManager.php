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

namespace morfeditorial;

use Doctrine\DBAL\Connection;
use Exception;

class DatabaseManager
{
    private Connection $db;

    private const STATE_PRIVATE = 'private';

    private const STATE_PUBLIC = 'public';

    public function __construct(Connection $connection)
    {
        $this->db = $connection;
        $this->initializeDatabaseTables();
    }

    private function initializeDatabaseTables() : void
    {
        $schemaManager = $this->db->createSchemaManager();

        if (!$schemaManager->tablesExist(['authors'])) {
            $this->db->executeStatement("
                CREATE TABLE authors (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    biography TEXT DEFAULT NULL,
                    channel_link TEXT DEFAULT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    state TEXT
                )
            ");

            $this->db->executeStatement("
                CREATE TABLE content (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    author_id INTEGER,
                    description TEXT,
                    tags TEXT,
                    FOREIGN KEY (author_id) REFERENCES authors(id)
                )
            ");

            $this->db->executeStatement("
                CREATE TABLE user_data (
                    user_id INTEGER PRIMARY KEY,
                    user_state TEXT,
                    current_panel INTEGER,
                    current_page TEXT,
                    role TEXT
                )
            ");

            $this->db->executeStatement("
                CREATE TABLE user_states (
                    user_id INTEGER,
                    state_key TEXT,
                    state_value TEXT,
                    PRIMARY KEY (user_id, state_key)
                )
            ");

            $this->db->executeStatement("
                CREATE TABLE roles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    role_name TEXT NOT NULL UNIQUE,
                    priority INTEGER NOT NULL
                )
            ");

            $this->db->executeStatement("
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
        return $this->db;
    }

    public function close() : void
    {
        $this->db->close();
    }

    public function createAuthor(string $name) : int
    {
        $this->db->executeStatement(
            'INSERT INTO authors (name, state) VALUES (?, ?)',
            [trim($name), self::STATE_PRIVATE]
        );

        return (int) $this->db->lastInsertId();
    }

    public function deleteAuthor(int $authorId) : void
    {
        $this->db->executeStatement('DELETE FROM authors WHERE id = ?', [$authorId]);
    }

    public function getAuthorById(int $authorId) : ?array
    {
        return $this->db->fetchAssociative('SELECT * FROM authors WHERE id = ?', [$authorId]) ?: null;
    }

    public function getAllAuthors() : array
    {
        return $this->db->fetchAllAssociative('SELECT * FROM authors');
    }

    public function getContentByAuthorId(int $authorId) : array
    {
        return $this->db->fetchAllAssociative('SELECT description FROM content WHERE author_id = ?', [$authorId]);
    }

    public function updateAuthorName(int $authorId, string $name) : void
    {
        $this->db->executeStatement('UPDATE authors SET name = ? WHERE id = ?', [trim($name), $authorId]);
    }

    public function setBiography(int $authorId, string $biography) : void
    {
        $this->db->executeStatement('UPDATE authors SET biography = ? WHERE id = ?', [trim($biography), $authorId]);
    }

    public function setChannelLink(int $authorId, string $link) : void
    {
        $this->db->executeStatement('UPDATE authors SET channel_link = ? WHERE id = ?', [trim($link), $authorId]);
    }

    public function setPrivate(int $authorId, bool $private = true) : void
    {
        $state = $private ? self::STATE_PRIVATE : self::STATE_PUBLIC;
        $this->db->executeStatement('UPDATE authors SET state = ? WHERE id = ?', [$state, $authorId]);
    }

    public function isPrivate(int $authorId) : bool
    {
        $result = $this->db->fetchAssociative('SELECT state FROM authors WHERE id = ?', [$authorId]);

        return $result && self::STATE_PRIVATE === $result['state'];
    }

    public function getAuthorCreationTime(int $authorId) : ?string
    {
        $result = $this->db->fetchAssociative('SELECT created_at FROM authors WHERE id = ?', [$authorId]);

        return $result['created_at'] ?? null;
    }

    public function countAuthors() : int
    {
        return (int) $this->db->fetchOne('SELECT COUNT(*) FROM authors');
    }

    public function setState(int $userId, $value, string $key = 'default') : void
    {
        // SQLite specific ON CONFLICT
        $this->db->executeStatement(
            'INSERT INTO user_states (user_id, state_key, state_value) VALUES (?, ?, ?) 
                                    ON CONFLICT(user_id, state_key) DO UPDATE SET state_value = excluded.state_value',
            [$userId, $key, json_encode($value)]
        );
    }

    public function getState(int $userId, string $key = 'default') : mixed
    {
        $row = $this->db->fetchAssociative('SELECT state_value FROM user_states WHERE user_id = ? AND state_key = ?', [$userId, $key]);

        return $row ? json_decode($row['state_value'], true) : null;
    }

    public function clearState(int $userId, ?string $key = null) : void
    {
        if ($key) {
            $this->db->executeStatement('DELETE FROM user_states WHERE user_id = ? AND state_key = ?', [$userId, $key]);
        } else {
            $this->db->executeStatement('DELETE FROM user_states WHERE user_id = ?', [$userId]);
        }
    }

    public function setCurrentPanel(int $userId, int $messageId) : void
    {
        $this->db->executeStatement('INSERT OR REPLACE INTO user_data (user_id, current_panel) VALUES (?, ?)', [$userId, $messageId]);
    }

    public function getCurrentPanel(int $userId) : ?int
    {
        $row = $this->db->fetchAssociative('SELECT current_panel FROM user_data WHERE user_id = ?', [$userId]);

        return $row ? (int) $row['current_panel'] : null;
    }

    public function setCurrentPage(int $userId, string $page) : void
    {
        $this->db->executeStatement('UPDATE user_data SET current_page = ? WHERE user_id = ?', [$page, $userId]);
    }

    public function getCurrentPage(int $userId) : ?string
    {
        $row = $this->db->fetchAssociative('SELECT current_page FROM user_data WHERE user_id = ?', [$userId]);

        return $row ? $row['current_page'] : null;
    }

    public function resetCurrentPage(int $userId) : void
    {
        $this->db->executeStatement('UPDATE user_data SET current_page = NULL WHERE user_id = ?', [$userId]);
    }

    public function createRole(string $roleName, int $priority) : bool
    {
        return (bool) $this->db->executeStatement('INSERT INTO roles (role_name, priority) VALUES (?, ?)', [$roleName, $priority]);
    }

    public function deleteRole(string $roleName) : bool
    {
        return (bool) $this->db->executeStatement('DELETE FROM roles WHERE role_name = ?', [$roleName]);
    }

    public function assignRole(int $userId, string $roleName) : bool
    {
        $role = $this->getRoleByName($roleName);
        if (! $role) {
            return false;
        }

        $exists = $this->db->fetchOne('SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = ?', [$userId, $role['id']]);

        if ($exists > 0) {
            return false;
        }

        return (bool) $this->db->executeStatement('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $role['id']]);
    }

    public function removeUserRole(int $userId, string $roleName) : bool
    {
        $role = $this->getRoleByName($roleName);
        if (! $role) {
            return false;
        }

        return (bool) $this->db->executeStatement('DELETE FROM user_roles WHERE user_id = ? AND role_id = ?', [$userId, $role['id']]);
    }

    public function hasRole(int $userId, string $roleName) : bool
    {
        $result = $this->db->fetchAssociative('
            SELECT ur.user_id FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE ur.user_id = ? AND r.role_name = ?
        ', [$userId, $roleName]);

        return (bool) $result;
    }

    public function hasHigherRole(int $userId, string $roleName) : bool
    {
        $role = $this->db->fetchAssociative('SELECT priority FROM roles WHERE role_name = ?', [$roleName]);

        if ($role) {
            $requiredPriority = $role['priority'];

            $userPriority = $this->db->fetchOne('
                SELECT MAX(r.priority) as max_priority FROM user_roles ur 
                JOIN roles r ON ur.role_id = r.id 
                WHERE ur.user_id = ?
            ', [$userId]);

            if (null !== $userPriority) {
                return $userPriority >= $requiredPriority;
            }
        }

        return false;
    }

    public function getAllRoles() : array
    {
        return $this->db->fetchAllAssociative('SELECT * FROM roles ORDER BY priority DESC');
    }

    public function getUsersCountByRole(string $roleName) : int
    {
        return (int) $this->db->fetchOne('SELECT COUNT(ur.user_id) AS user_count 
                  FROM user_roles ur
                  JOIN roles r ON ur.role_id = r.id
                  WHERE r.role_name = ?', [$roleName]);
    }

    public function getRoleByName(string $roleName) : ?array
    {
        return $this->db->fetchAssociative('SELECT * FROM roles WHERE role_name = ?', [$roleName]) ?: null;
    }

    public function getRolePriority(string $roleName) : int
    {
        return (int) $this->db->fetchOne('SELECT priority FROM roles WHERE role_name = ?', [$roleName]);
    }

    public function getRolesCount() : int
    {
        return (int) $this->db->fetchOne("SELECT COUNT(*) FROM roles");
    }

    public function updateRolePriority(string $roleName, int $priority) : bool
    {
        return (bool) $this->db->executeStatement('UPDATE roles SET priority = ? WHERE role_name = ?', [$priority, $roleName]);
    }

    public function updateRolePriorities(string $roleName, int $newPriority) : void
    {
        $currentPriority = $this->db->fetchOne("SELECT priority FROM roles WHERE role_name = ?", [$roleName]);
        if (false === $currentPriority) {
            throw new Exception("Роль не знайдена.");
        }

        $this->db->beginTransaction();

        try {
            if ($currentPriority < $newPriority) {
                $this->db->executeStatement('UPDATE roles SET priority = priority - 1 WHERE priority > ? AND priority <= ?', [$currentPriority, $newPriority]);
            } elseif ($currentPriority > $newPriority) {
                $this->db->executeStatement('UPDATE roles SET priority = priority + 1 WHERE priority < ? AND priority >= ?', [$currentPriority, $newPriority]);
            } else {
                return;
            }

            $this->db->executeStatement('UPDATE roles SET priority = ? WHERE role_name = ?', [$newPriority, $roleName]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function queryRolesOrderedByPriority() : array
    {
        return $this->db->fetchAllAssociative("SELECT role_name, priority FROM roles ORDER BY priority DESC");
    }

    public function beginTransaction()
    {
        $this->db->beginTransaction();
    }

    public function commitTransaction()
    {
        $this->db->commit();
    }

    public function rollbackTransaction()
    {
        $this->db->rollBack();
    }
}
