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

namespace morfeditorial\services;

use Exception;
use morfeditorial\storage\StorageInterface;

class RoleService
{
    private $db;

    public function __construct(private StorageInterface $storage)
    {
        $this->db = $storage->getConnection();
    }

    public function createRole(string $roleName, int $priority) : bool
    {
        $this->db->executeStatement(
            'INSERT INTO roles (role_name, priority) VALUES (?, ?)',
            [$roleName, $priority]
        );

        return true;
    }

    public function deleteRole(string $roleName) : bool
    {
        $this->db->executeStatement(
            'DELETE FROM roles WHERE role_name = ?',
            [$roleName]
        );

        return true;
    }

    public function getAllRoles() : array
    {
        return $this->db->fetchAllAssociative('SELECT * FROM roles ORDER BY priority DESC');
    }

    public function getRoleByName(string $roleName) : ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM roles WHERE role_name = ?',
            [$roleName]
        );

        return false !== $result ? $result : null;
    }

    public function getRolePriority(string $roleName) : int
    {
        $result = $this->db->fetchOne(
            'SELECT priority FROM roles WHERE role_name = ?',
            [$roleName]
        );

        return false !== $result ? (int) $result : 0;
    }

    public function getRolesCount() : int
    {
        return (int) $this->db->fetchOne('SELECT COUNT(*) FROM roles');
    }

    public function updateRolePriority(string $roleName, int $priority) : bool
    {
        $this->db->executeStatement(
            'UPDATE roles SET priority = ? WHERE role_name = ?',
            [$priority, $roleName]
        );

        return true;
    }

    public function updateRolePriorities(string $roleName, int $newPriority) : void
    {
        $currentPriority = $this->getRolePriority($roleName);

        if (0 === $currentPriority) {
            throw new Exception('Role not found.');
        }

        $this->db->beginTransaction();
        try {
            if ($currentPriority < $newPriority) {
                $roles = $this->db->fetchAllAssociative(
                    'SELECT id, priority FROM roles WHERE priority > ? AND priority <= ?',
                    [$currentPriority, $newPriority]
                );
            } elseif ($currentPriority > $newPriority) {
                $roles = $this->db->fetchAllAssociative(
                    'SELECT id, priority FROM roles WHERE priority < ? AND priority >= ?',
                    [$currentPriority, $newPriority]
                );
            } else {
                $roles = [];
            }

            foreach ($roles as $role) {
                $newRolePriority = ($currentPriority < $newPriority) ? $role['priority'] - 1 : $role['priority'] + 1;
                $this->db->executeStatement(
                    'UPDATE roles SET priority = ? WHERE id = ?',
                    [$newRolePriority, $role['id']]
                );
            }

            $this->updateRolePriority($roleName, $newPriority);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function queryRolesOrderedByPriority() : array
    {
        return $this->getAllRoles();
    }
}
