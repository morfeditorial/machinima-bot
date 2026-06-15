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

use Doctrine\DBAL\Connection;
use Exception;
use morfeditorial\storage\StorageInterface;

class RoleService
{
    private Connection $db;

    public function __construct(private StorageInterface $storage)
    {
        $this->db = $storage->getConnection();
    }

    public function createRole(string $role_name, int $priority) : bool
    {
        return (bool) $this->db->executeStatement(
            'INSERT INTO roles (role_name, priority) VALUES (?, ?)',
            [$role_name, $priority]
        );
    }

    public function deleteRole(string $role_name) : bool
    {
        return (bool) $this->db->executeStatement(
            'DELETE FROM roles WHERE role_name = ?',
            [$role_name]
        );
    }

    public function assignRole(int $user_id, string $role_name) : bool
    {
        $role = $this->getRoleByName($role_name);
        if (! $role) {
            return false;
        }

        $exists = $this->db->fetchOne(
            'SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = ?',
            [$user_id, $role['id']]
        );

        if ($exists > 0) {
            return false;
        }

        return (bool) $this->db->executeStatement(
            'INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)',
            [$user_id, $role['id']]
        );
    }

    public function removeUserRole(int $user_id, string $role_name) : bool
    {
        $role = $this->getRoleByName($role_name);
        if (! $role) {
            return false;
        }

        return (bool) $this->db->executeStatement(
            'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?',
            [$user_id, $role['id']]
        );
    }

    public function hasRole(int $user_id, string $role_name) : bool
    {
        $result = $this->db->fetchAssociative('
            SELECT ur.user_id FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.role_name = ?
        ', [$user_id, $role_name]);

        return (bool) $result;
    }

    public function hasHigherRole(int $user_id, string $role_name) : bool
    {
        $role = $this->db->fetchAssociative(
            'SELECT priority FROM roles WHERE role_name = ?',
            [$role_name]
        );

        if ($role) {
            $required_priority = $role['priority'];

            $user_priority = $this->db->fetchOne('
                SELECT MAX(r.priority) as max_priority FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                WHERE ur.user_id = ?
            ', [$user_id]);

            if (null !== $user_priority) {
                return $user_priority >= $required_priority;
            }
        }

        return false;
    }

    public function getUsersCountByRole(string $role_name) : int
    {
        return (int) $this->db->fetchOne('
            SELECT COUNT(ur.user_id) AS user_count
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE r.role_name = ?
        ', [$role_name]);
    }

    public function getAllRoles() : array
    {
        return $this->db->fetchAllAssociative('SELECT * FROM roles ORDER BY priority DESC');
    }

    public function getRoleByName(string $role_name) : ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM roles WHERE role_name = ?',
            [$role_name]
        );

        return false !== $result ? $result : null;
    }

    public function getRolePriority(string $role_name) : int
    {
        return (int) $this->db->fetchOne(
            'SELECT priority FROM roles WHERE role_name = ?',
            [$role_name]
        );
    }

    public function getRolesCount() : int
    {
        return (int) $this->db->fetchOne('SELECT COUNT(*) FROM roles');
    }

    public function updateRolePriority(string $role_name, int $priority) : bool
    {
        return (bool) $this->db->executeStatement(
            'UPDATE roles SET priority = ? WHERE role_name = ?',
            [$priority, $role_name]
        );
    }

    public function updateRolePriorities(string $role_name, int $new_priority) : void
    {
        $current_priority = $this->getRolePriority($role_name);
        if (0 === $current_priority && ! $this->getRoleByName($role_name)) {
            throw new Exception('Role not found.');
        }

        $this->db->beginTransaction();

        try {
            if ($current_priority < $new_priority) {
                $this->db->executeStatement(
                    'UPDATE roles SET priority = priority - 1 WHERE priority > ? AND priority <= ?',
                    [$current_priority, $new_priority]
                );
            } elseif ($current_priority > $new_priority) {
                $this->db->executeStatement(
                    'UPDATE roles SET priority = priority + 1 WHERE priority < ? AND priority >= ?',
                    [$current_priority, $new_priority]
                );
            } else {
                return;
            }

            $this->updateRolePriority($role_name, $new_priority);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function queryRolesOrderedByPriority() : array
    {
        return $this->db->fetchAllAssociative(
            "SELECT role_name, priority FROM roles ORDER BY priority DESC"
        );
    }
}
