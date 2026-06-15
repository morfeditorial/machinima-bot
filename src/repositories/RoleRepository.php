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

namespace morfeditorial\repositories;

class RoleRepository
{
    public function __construct(private StorageInterface $storage) {}

    public function createRole(string $roleName, int $priority) : bool
    {
        try {
            $this->storage->execute(
                'INSERT INTO roles (role_name, priority) VALUES (:name, :priority)',
                ['name' => $roleName, 'priority' => $priority]
            );

            return true;
        } catch (Exception) {
            return false;
        }
    }

    public function deleteRole(string $roleName) : bool
    {
        $this->storage->execute(
            'DELETE FROM roles WHERE role_name = :name',
            ['name' => $roleName]
        );

        return $this->storage->query('SELECT ROW_COUNT()')[0]['ROW_COUNT()'] > 0;
    }

    public function getAllRoles() : array
    {
        return $this->storage->query(
            'SELECT * FROM roles ORDER BY priority DESC'
        );
    }

    public function getRoleByName(string $roleName) : ?array
    {
        $result = $this->storage->query(
            'SELECT * FROM roles WHERE role_name = :name LIMIT 1',
            ['name' => $roleName]
        );

        return $result[0] ?? null;
    }

    public function getRolePriority(string $roleName) : int
    {
        $result = $this->storage->query(
            'SELECT priority FROM roles WHERE role_name = :name LIMIT 1',
            ['name' => $roleName]
        );

        return (int) ($result[0]['priority'] ?? 0);
    }

    public function getRolesCount() : int
    {
        return (int) ($this->storage->query('SELECT COUNT(*) as count FROM roles')[0]['count'] ?? 0);
    }

    public function updateRolePriority(string $roleName, int $priority) : bool
    {
        $this->storage->execute(
            'UPDATE roles SET priority = :priority WHERE role_name = :name',
            ['name' => $roleName, 'priority' => $priority]
        );

        return $this->storage->query('SELECT ROW_COUNT()')[0]['ROW_COUNT()'] > 0;
    }

    public function updateRolePriorities(string $roleName, int $newPriority) : void
    {
        $currentPriority = $this->getRolePriority($roleName);
        if (0 === $currentPriority) {
            throw new Exception('Role not found.');
        }

        $this->storage->beginTransaction();
        try {
            if ($currentPriority < $newPriority) {
                $this->storage->execute(
                    'UPDATE roles SET priority = priority - 1 WHERE priority > :current_priority AND priority <= :new_priority',
                    ['current_priority' => $currentPriority, 'new_priority' => $newPriority]
                );
            } elseif ($currentPriority > $newPriority) {
                $this->storage->execute(
                    'UPDATE roles SET priority = priority + 1 WHERE priority < :current_priority AND priority >= :new_priority',
                    ['current_priority' => $currentPriority, 'new_priority' => $newPriority]
                );
            }
            $this->updateRolePriority($roleName, $newPriority);
            $this->storage->commitTransaction();
        } catch (Exception $e) {
            $this->storage->rollbackTransaction();
            throw $e;
        }
    }

    public function queryRolesOrderedByPriority() : array
    {
        return $this->storage->query('SELECT * FROM roles ORDER BY priority DESC');
    }
}
