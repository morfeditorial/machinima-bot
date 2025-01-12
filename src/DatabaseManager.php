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

namespace morfeditorial;

use SQLite3;

class DatabaseManager
{
    private SQLite3 $db;

    private const STATE_PRIVATE = 'private';

    private const STATE_PUBLIC = 'public';

    /**
     * Constructor to initialize the database connection.
     *
     * @param  string  $dbName  The name of the database file.
     */
    public function __construct(string $dbName)
    {
        $this->db = new SQLite3($dbName);
        $this->initializeDatabaseTables();
    }

    /**
     * Initialize the database tables if they do not exist.
     */
    private function initializeDatabaseTables() : void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS authors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                biography TEXT DEFAULT NULL,
                channel_link TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                state TEXT
            );

            CREATE TABLE IF NOT EXISTS content (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                author_id INTEGER,
                description TEXT,
                tags TEXT,
                FOREIGN KEY (author_id) REFERENCES authors(id)
            );

            CREATE TABLE IF NOT EXISTS user_data (
                user_id INTEGER PRIMARY KEY,
                user_state TEXT,
                current_panel INTEGER,
                current_page TEXT
            );

            CREATE TABLE IF NOT EXISTS user_states (
                user_id INTEGER,
                state_key TEXT,
                state_value TEXT,
                PRIMARY KEY (user_id, state_key)
            );

            CREATE TABLE IF NOT EXISTS roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_name TEXT NOT NULL UNIQUE,
                priority INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS user_roles (
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (role_id) REFERENCES roles(id),
                PRIMARY KEY (user_id)
            );
        ");
    }

    /**
     * Get the database connection instance.
     *
     * @return SQLite3 The SQLite3 database connection.
     */
    public function getConnection() : SQLite3
    {
        return $this->db;
    }

    /**
     * Close the database connection.
     */
    public function close() : void
    {
        $this->db->close();
    }

    /**
     * Create a new author and return the author's ID.
     *
     * @param  string  $name  The name of the author to be created.
     * @return int The ID of the newly created author.
     */
    public function createAuthor(string $name) : int
    {
        $stmt = $this->db->prepare('INSERT INTO authors (name, state) VALUES (:name, :state)');
        $stmt->bindValue(':name', trim($name), SQLITE3_TEXT);
        $stmt->bindValue(':state', self::STATE_PRIVATE, SQLITE3_TEXT);
        $stmt->execute();

        return $this->db->lastInsertRowID();
    }

    /**
     * Delete an author by ID.
     *
     * @param  int  $authorId  The ID of the author to be deleted.
     */
    public function deleteAuthor(int $authorId) : void
    {
        $stmt = $this->db->prepare('DELETE FROM authors WHERE id = :author_id');
        $stmt->bindValue(':author_id', $authorId, SQLITE3_INTEGER);
        $stmt->execute();
    }

    /**
     * Retrieve an author by ID.
     *
     * @param  int  $authorId  The ID of the author to be retrieved.
     * @return array|null The author's data if found, null otherwise.
     */
    public function getAuthorById(int $authorId) : ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM authors WHERE id = :author_id');
        $stmt->bindValue(':author_id', $authorId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        return $result->fetchArray(SQLITE3_ASSOC) ?: null;
    }

    /**
     * Retrieve all authors.
     *
     * @return array An array of all authors.
     */
    public function getAllAuthors() : array
    {
        $result = $this->db->query('SELECT * FROM authors');
        $authors = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $authors[] = $row;
        }

        return $authors;
    }

    /**
     * Retrieve content associated with a specific author by their ID.
     *
     * @param  int  $authorId  The ID of the author whose content is to be retrieved.
     * @return array An array of content associated with the author.
     */
    public function getContentByAuthorId(int $authorId) : array
    {
        $content = [];
        $stmt = $this->db->prepare('SELECT title, description FROM content WHERE author_id = :author_id');
        $stmt->bindValue(':author_id', $authorId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $content[] = $row;
        }

        return $content;
    }

    /**
     * Update the name of an author.
     *
     * @param  int  $authorId  The ID of the author to be updated.
     * @param  string  $name  The new name of the author.
     */
    public function updateAuthorName(int $authorId, string $name) : void
    {
        $stmt = $this->db->prepare('UPDATE authors SET name = :name WHERE id = :author_id');
        $stmt->bindValue(':author_id', $authorId, SQLITE3_INTEGER);
        $stmt->bindValue(':name', trim($name), SQLITE3_TEXT);
        $stmt->execute();
    }

    /**
     * Set the biography of an author.
     *
     * @param  int  $authorId  The ID of the author to be updated.
     * @param  string  $biography  The biography of the author.
     */
    public function setBiography(int $authorId, string $biography) : void
    {
        $stmt = $this->db->prepare('UPDATE authors SET biography = :biography WHERE id = :author_id');
        $stmt->bindValue(':author_id', $authorId, SQLITE3_INTEGER);
        $stmt->bindValue(':biography', trim($biography), SQLITE3_TEXT);
        $stmt->execute();
    }

    /**
     * Set the channel link of an author.
     *
     * @param  int  $authorId  The ID of the author to be updated.
     * @param  string  $link  The channel link of the author.
     */
    public function setChannelLink(int $authorId, string $link) : void
    {
        $stmt = $this->db->prepare('UPDATE authors SET channel_link = :link WHERE id = :author_id');
        $stmt->bindValue(':author_id', $authorId, SQLITE3_INTEGER);
        $stmt->bindValue(':link', trim($link), SQLITE3_TEXT);
        $stmt->execute();
    }

    /**
     * Set the state of an author to private or public.
     *
     * @param  int  $authorId  The ID of the author to be updated.
     * @param  bool  $private  Whether the author should be private or not.
     */
    public function setPrivate(int $authorId, bool $private = true) : void
    {
        $state = $private ? self::STATE_PRIVATE : self::STATE_PUBLIC;
        $stmt = $this->db->prepare('UPDATE authors SET state = :state WHERE id = :author_id');
        $stmt->bindValue(':author_id', $authorId, SQLITE3_INTEGER);
        $stmt->bindValue(':state', $state, SQLITE3_TEXT);
        $stmt->execute();
    }

    /**
     * Check if an author is private.
     *
     * @param  int  $authorId  The ID of the author to be checked.
     * @return bool Whether the author is private or not.
     */
    public function isPrivate(int $authorId) : bool
    {
        $stmt = $this->db->prepare('SELECT state FROM authors WHERE id = :author_id');
        $stmt->bindValue(':author_id', $authorId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        return self::STATE_PRIVATE === $result->fetchArray(SQLITE3_ASSOC)['state'];
    }

    /**
     * Retrieve the creation time of an author.
     *
     * @param  int  $authorId  The ID of the author whose creation time is to be retrieved.
     * @return string|null The creation time of the author if found, null otherwise.
     */
    public function getAuthorCreationTime(int $authorId) : ?string
    {
        $stmt = $this->db->prepare('SELECT created_at FROM authors WHERE id = :author_id');
        $stmt->bindValue(':author_id', $authorId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        return $result->fetchArray(SQLITE3_ASSOC)['created_at'] ?? null;
    }

    /**
     * Count the total number of authors.
     *
     * @return int The total number of authors.
     */
    public function countAuthors() : int
    {
        return (int) $this->db->querySingle('SELECT COUNT(*) FROM authors');
    }

    /**
     * Set the state for a user.
     *
     * @param  int  $userId  The ID of the user whose state is being set.
     * @param  mixed  $value  The value to be stored as the user's state.
     * @param  string  $key  The key under which the state is stored (default is "default").
     */
    public function setState(int $userId, $value, string $key = 'default') : void
    {
        $stmt = $this->db->prepare('INSERT INTO user_states (user_id, state_key, state_value) VALUES (:user_id, :state_key, :state_value) 
                                    ON CONFLICT(user_id, state_key) DO UPDATE SET state_value = :state_value');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':state_key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':state_value', json_encode($value), SQLITE3_TEXT);
        $stmt->execute();
    }

    /**
     * Get the state of a user.
     *
     * @param  int  $userId  The ID of the user whose state is being retrieved.
     * @param  string  $key  The key of the state to retrieve (default is "default").
     * @return mixed The value of the user's state or null if not found.
     */
    public function getState(int $userId, string $key = 'default') : mixed
    {
        $stmt = $this->db->prepare('SELECT state_value FROM user_states WHERE user_id = :user_id AND state_key = :state_key');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':state_key', $key, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        return $row ? json_decode($row['state_value'], true) : null;
    }

    /**
     * Clear the state of a user.
     *
     * @param  int  $userId  The ID of the user whose state is being cleared.
     * @param  string|null  $key  The key of the state to clear (if null, clears all states).
     */
    public function clearState(int $userId, ?string $key = null) : void
    {
        if ($key) {
            $stmt = $this->db->prepare('DELETE FROM user_states WHERE user_id = :user_id AND state_key = :state_key');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':state_key', $key, SQLITE3_TEXT);
        } else {
            $stmt = $this->db->prepare('DELETE FROM user_states WHERE user_id = :user_id');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        }
        $stmt->execute();
    }

    /**
     * Set the current panel for a user.
     *
     * @param  int  $userId  The ID of the user.
     * @param  int  $messageId  The ID of the message representing the current panel.
     */
    public function setCurrentPanel(int $userId, int $messageId) : void
    {
        $stmt = $this->db->prepare('INSERT OR REPLACE INTO user_data (user_id, current_panel) VALUES (:user_id, :message_id)');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':message_id', $messageId, SQLITE3_INTEGER);
        $stmt->execute();
    }

    /**
     * Get the current panel for a user.
     *
     * @param  int  $userId  The ID of the user.
     * @return int|null The ID of the current panel or null if not found.
     */
    public function getCurrentPanel(int $userId) : ?int
    {
        $stmt = $this->db->prepare('SELECT current_panel FROM user_data WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        return $row ? (int) $row['current_panel'] : null;
    }

    /**
     * Set the current page for a user.
     *
     * @param  int  $userId  The ID of the user.
     * @param  string  $page  The current page.
     */
    public function setCurrentPage(int $userId, string $page) : void
    {
        $stmt = $this->db->prepare('UPDATE user_data SET current_page = :page WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':page', $page, SQLITE3_TEXT);
        $stmt->execute();
    }

    /**
     * Get the current page for a user.
     *
     * @param  int  $userId  The ID of the user.
     * @return string|null The current page or null if not found.
     */
    public function getCurrentPage(int $userId) : ?string
    {
        $stmt = $this->db->prepare('SELECT current_page FROM user_data WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        return $row ? $row['current_page'] : null;
    }

    /**
     * Reset the current page for a user.
     *
     * @param  int  $userId  The ID of the user.
     */
    public function resetCurrentPage(int $userId) : void
    {
        $stmt = $this->db->prepare('UPDATE user_data SET current_page = NULL WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->execute();
    }

    /**
     * Create a new role with specified name and priority.
     *
     * @param  string  $roleName  The name of the role to be created.
     * @param  int  $priority  The priority level of the role.
     * @return bool Returns true on success, false on failure.
     */
    public function createRole(string $roleName, int $priority) : bool
    {
        $stmt = $this->db->prepare('INSERT INTO roles (role_name, priority) VALUES (:role_name, :priority)');
        $stmt->bindValue(':role_name', $roleName, SQLITE3_TEXT);
        $stmt->bindValue(':priority', $priority, SQLITE3_INTEGER);

        return $stmt->execute() ? true : false;
    }

    /**
     * Delete a role by name.
     *
     * @param  string  $roleName  The name of the role to be deleted.
     * @return bool Returns true on success, false on failure.
     */
    public function deleteRole(string $roleName) : bool
    {
        $stmt = $this->db->prepare('DELETE FROM roles WHERE role_name = :role_name');
        $stmt->bindValue(':role_name', $roleName, SQLITE3_TEXT);

        return $stmt->execute() ? true : false;
    }

    /**
     * Assign a specified role to a user.
     *
     * @param  int  $userId  The ID of the user to whom the role will be assigned.
     * @param  string  $roleName  The name of the role to be assigned.
     * @return bool Returns true on success, false on failure.
     */
    public function assignRole(int $userId, string $roleName) : bool
    {
        $role = $this->getRoleByName($roleName);
        if (! $role) {
            return false;
        }

        $checkStmt = $this->db->prepare('SELECT COUNT(*) FROM user_roles WHERE user_id = :user_id AND role_id = :role_id');
        $checkStmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $checkStmt->bindValue(':role_id', $role['id'], SQLITE3_INTEGER);
        $exists = $checkStmt->execute()->fetchArray(SQLITE3_NUM)[0];

        if ($exists > 0) {
            return false;
        }

        $stmt = $this->db->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':role_id', $role['id'], SQLITE3_INTEGER);

        return $stmt->execute() ? true : false;
    }

    /**
     * Remove a specified role from a user.
     *
     * @param  int  $userId  The ID of the user from whom the role will be removed.
     * @param  string  $roleName  The name of the role to be removed.
     * @return bool Returns true on success, false on failure.
     */
    public function removeUserRole(int $userId, string $roleName) : bool
    {
        $role = $this->getRoleByName($roleName);
        if (! $role) {
            return false;
        }
        $stmt = $this->db->prepare('DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':role_id', $role['id'], SQLITE3_INTEGER);

        return $stmt->execute() ? true : false;
    }

    /**
     * Check if a user has a specific role.
     *
     * @param  int  $userId  The ID of the user.
     * @param  string  $roleName  The name of the role to check.
     * @return bool True if the user has the role, false otherwise.
     */
    public function hasRole(int $userId, string $roleName) : bool
    {
        $stmt = $this->db->prepare('
            SELECT ur.user_id FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE ur.user_id = :user_id AND r.role_name = :role_name
        ');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':role_name', $roleName, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        return (bool) $result;
    }

    /**
     * Check if a user has a higher role than a specified role.
     *
     * @param  int  $userId  The ID of the user.
     * @param  string  $roleName  The name of the role to compare.
     * @return bool True if the user has a higher role, false otherwise.
     */
    public function hasHigherRole(int $userId, string $roleName) : bool
    {
        $stmt = $this->db->prepare('SELECT priority FROM roles WHERE role_name = :role_name');
        $stmt->bindValue(':role_name', $roleName, SQLITE3_TEXT);
        $role = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($role) {
            $requiredPriority = $role['priority'];

            $stmt = $this->db->prepare('
                SELECT MAX(r.priority) as max_priority FROM user_roles ur 
                JOIN roles r ON ur.role_id = r.id 
                WHERE ur.user_id = :user_id
            ');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

            if ($result) {
                $userPriority = $result['max_priority'];

                return $userPriority >= $requiredPriority;
            }
        }

        return false;
    }

    /**
     * Retrieve all roles ordered by priority.
     *
     * @return array An array of roles ordered by priority.
     */
    public function getAllRoles() : array
    {
        $result = $this->db->query('SELECT * FROM roles ORDER BY priority DESC');
        $roles = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $roles[] = $row;
        }

        return $roles;
    }

    /**
     * Get the number of users with a specific role.
     *
     * @param  string  $roleName  Role name.
     * @return int Number of users with the specified role.
     */
    public function getUsersCountByRole(string $roleName) : int
    {
        $stmt = $this->db->prepare('SELECT COUNT(ur.user_id) AS user_count 
                  FROM user_roles ur
                  JOIN roles r ON ur.role_id = r.id
                  WHERE r.role_name = :role_name');
        $stmt->bindValue(':role_name', $roleName, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        return $result ? (int) $result['user_count'] : 0;
    }

    /**
     * Retrieve a role by its name.
     *
     * @param  string  $roleName  The name of the role to be retrieved.
     * @return array|null The role data if found, null otherwise.
     */
    public function getRoleByName(string $roleName) : ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM roles WHERE role_name = :role_name');
        $stmt->bindValue(':role_name', $roleName, SQLITE3_TEXT);
        $result = $stmt->execute();

        return $result->fetchArray(SQLITE3_ASSOC) ?: null;
    }

    /**
     * Retrieve priority of a specified role.
     *
     * @param  string  $roleName  The name of the role for which priority is to be retrieved.
     * @return int The priority of the role.
     */
    public function getRolePriority(string $roleName) : int
    {
        $stmt = $this->db->prepare('SELECT priority FROM roles WHERE role_name = :role_name');
        $stmt->bindValue(':role_name', $roleName, SQLITE3_TEXT);

        return $stmt->execute()->fetchArray(SQLITE3_ASSOC)['priority'];
    }

    /**
     * Count roles.
     *
     * @return int The count of roles.
     */
    public function getRolesCount() : int
    {
        return $this->db->querySingle("SELECT COUNT(*) FROM roles");
    }

    /**
     * Update priority of a specified role.
     *
     * @param  string  $roleName  The name of the role for which priority is to be updated.
     * @param  int  $priority  The new priority level of the role.
     * @return bool Returns true on success, false on failure.
     */
    public function updateRolePriority(string $roleName, int $priority) : bool
    {
        $stmt = $this->db->prepare('UPDATE roles SET priority = :priority WHERE role_name = :role_name');
        $stmt->bindValue(':role_name', $roleName, SQLITE3_TEXT);
        $stmt->bindValue(':priority', $priority, SQLITE3_INTEGER);

        return $stmt->execute() ? true : false;
    }

    /**
     * Update priorities for roles within specified range.
     *
     * @param  int  $selectedRolePriority  The priority of the selected role.
     * @param  int  $targetPriority  The target priority level.
     */
public function updateRolePriorities(string $roleName, int $newPriority) : void
{
    $currentPriority = $this->db->querySingle("SELECT priority FROM roles WHERE role_name = '$roleName'");
    if ($currentPriority === false) {
        throw new Exception("Роль не знайдена.");
    }

    $this->beginTransaction();

    try {
        if ($currentPriority < $newPriority) {
            $stmt = $this->db->prepare('UPDATE roles SET priority = priority - 1 WHERE priority > :current_priority AND priority <= :new_priority');
        } elseif ($currentPriority > $newPriority) {
            $stmt = $this->db->prepare('UPDATE roles SET priority = priority + 1 WHERE priority < :current_priority AND priority >= :new_priority');
        } else {
            // Якщо пріоритети однакові, нічого не робимо
            return;
        }
        $stmt->bindValue(':current_priority', $currentPriority, SQLITE3_INTEGER);
        $stmt->bindValue(':new_priority', $newPriority, SQLITE3_INTEGER);
        $stmt->execute();

        // Оновлення пріоритету вибраної ролі
        $stmt = $this->db->prepare('UPDATE roles SET priority = :new_priority WHERE role_name = :role_name');
        $stmt->bindValue(':new_priority', $newPriority, SQLITE3_INTEGER);
        $stmt->bindValue(':role_name', $roleName, SQLITE3_TEXT);
        $stmt->execute();

        $this->commitTransaction();
    } catch (Exception $e) {
        $this->rollbackTransaction();
        throw $e;
    }
}

    public function queryRolesOrderedByPriority() : array
    {
        $result = $this->db->query("SELECT * FROM roles ORDER BY priority DESC");
        $roles = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $roles[] = [
                "role_name" => $row["role_name"],
                "priority" => $row["priority"]
            ];
        }
        return $roles;
    }

    public function beginTransaction()
    {
        $this->db->exec("BEGIN TRANSACTION");
    }

    public function commitTransaction()
    {
        $this->db->exec("COMMIT");
    }

    public function rollbackTransaction()
    {
        $this->db->exec("ROLLBACK");
    }

    public function prepareStatement(string $query)
    {
        return $this->db->prepare($query);
    }
}