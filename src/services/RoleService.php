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
use morfeditorial\storage\StorageInterface;

class RoleService
{
    private Connection $db;

    public function __construct(private StorageInterface $storage)
    {
        $this->db = $storage->getConnection();
    }

    public function createRole(string $role_name) : bool
    {
        return (bool) $this->db->executeStatement(
            'INSERT INTO roles (role_name) VALUES (?)',
            [$role_name]
        );
    }

    public function addParentChild(string $parent_role_name, string $child_role_name) : bool
    {
        $parent = $this->getRoleByName($parent_role_name);
        $child = $this->getRoleByName($child_role_name);

        if (! $parent || ! $child) {
            return false;
        }

        return (bool) $this->db->executeStatement(
            'INSERT OR IGNORE INTO role_hierarchy (parent_role_id, child_role_id) VALUES (?, ?)',
            [$parent['id'], $child['id']]
        );
    }

    public function removeParentChild(string $parent_role_name, string $child_role_name) : bool
    {
        $parent = $this->getRoleByName($parent_role_name);
        $child = $this->getRoleByName($child_role_name);

        if (! $parent || ! $child) {
            return false;
        }

        return (bool) $this->db->executeStatement(
            'DELETE FROM role_hierarchy WHERE parent_role_id = ? AND child_role_id = ?',
            [$parent['id'], $child['id']]
        );
    }

    public function getRoleHierarchy() : array
    {
        $rows = $this->db->fetchAllAssociative('
            SELECT p.role_name AS parent, c.role_name AS child
            FROM role_hierarchy rh
            JOIN roles p ON rh.parent_role_id = p.id
            JOIN roles c ON rh.child_role_id = c.id
        ');

        $hierarchy = [];

        foreach ($rows as $row) {
            $hierarchy[$row['parent']][] = $row['child'];
        }

        return $hierarchy;
    }

    public function deleteRole(string $role_name) : bool
    {
        $role = $this->getRoleByName($role_name);

        if (! $role) {
            return false;
        }

        $this->db->executeStatement(
            'DELETE FROM role_hierarchy WHERE parent_role_id = ? OR child_role_id = ?',
            [$role['id'], $role['id']]
        );

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

    public function getUserRoleNames(int $user_id) : array
    {
        $rows = $this->db->fetchAllAssociative('
            SELECT r.role_name FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
        ', [$user_id]);

        return array_map(fn (array $row) : string => $row['role_name'], $rows);
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
        return $this->db->fetchAllAssociative('SELECT * FROM roles');
    }

    public function getRoleByName(string $role_name) : ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM roles WHERE role_name = ?',
            [$role_name]
        );

        return false !== $result ? $result : null;
    }

    public function getRolesCount() : int
    {
        return (int) $this->db->fetchOne('SELECT COUNT(*) FROM roles');
    }

    public function getChildren(string $role_name) : array
    {
        $role = $this->getRoleByName($role_name);

        if (! $role) {
            return [];
        }

        return $this->db->fetchAllAssociative('
            SELECT r.id, r.role_name FROM role_hierarchy rh
            JOIN roles r ON rh.child_role_id = r.id
            WHERE rh.parent_role_id = ?
        ', [$role['id']]);
    }

    public function getParents(string $role_name) : array
    {
        $role = $this->getRoleByName($role_name);

        if (! $role) {
            return [];
        }

        return $this->db->fetchAllAssociative('
            SELECT r.id, r.role_name FROM role_hierarchy rh
            JOIN roles r ON rh.parent_role_id = r.id
            WHERE rh.child_role_id = ?
        ', [$role['id']]);
    }
}
