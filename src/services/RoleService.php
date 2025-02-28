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
 *
 */

declare(strict_types=1);

namespace morfeditorial\services;

use Exception;
use morfeditorial\interfaces\StorageInterface;

class RoleService
{
    private $queryBuilder;

    public function __construct(private StorageInterface $storage)
    {
        $this->queryBuilder = $storage->getQueryBuilder();
    }

    public function createRole(string $roleName, int $priority) : bool
    {
        $this->queryBuilder->insert('roles', [
            'role_name' => $roleName,
            'priority' => $priority,
        ])->execute();

        return true;
    }

    public function deleteRole(string $roleName) : bool
    {
        $this->queryBuilder->delete('roles')
            ->where('role_name', '=', $roleName)
            ->execute();

        return true;
    }

    public function getAllRoles() : array
    {
        return $this->queryBuilder->select(['*'])
            ->from('roles')
            ->orderBy('priority', 'DESC')
            ->get();
    }

    public function getRoleByName(string $roleName) : ?array
    {
        return $this->queryBuilder->select(['*'])
            ->from('roles')
            ->where('role_name', '=', $roleName)
            ->first();
    }

    public function getRolePriority(string $roleName) : int
    {
        $result = $this->queryBuilder->select(['priority'])
            ->from('roles')
            ->where('role_name', '=', $roleName)
            ->first();

        return $result['priority'] ?? 0;
    }

    public function getRolesCount() : int
    {
        return $this->queryBuilder->select()
            ->from('roles')
            ->count();
    }

    public function updateRolePriority(string $roleName, int $priority) : bool
    {
        $this->queryBuilder->update('roles', [
            'priority' => $priority,
        ])->where('role_name', '=', $roleName)
            ->execute();

        return true;
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
                $roles = $this->queryBuilder->select(['id', 'priority'])
                    ->from('roles')
                    ->where('priority', '>', $currentPriority)
                    ->andWhere('priority', '<=', $newPriority)
                    ->get();
            } elseif ($currentPriority > $newPriority) {
                $roles = $this->queryBuilder->select(['id', 'priority'])
                    ->from('roles')
                    ->where('priority', '<', $currentPriority)
                    ->andWhere('priority', '>=', $newPriority)
                    ->get();
            }

            foreach ($roles as $role) {
                $newRolePriority = ($currentPriority < $newPriority) ? $role['priority'] - 1 : $role['priority'] + 1;
                $this->queryBuilder->update('roles', [
                    'priority' => $newRolePriority,
                ])->where('id', '=', $role['id'])
                    ->execute();
            }

            $this->updateRolePriority($roleName, $newPriority);
            $this->storage->commit();
        } catch (Exception $e) {
            $this->storage->rollBack();
            throw $e;
        }
    }

    public function queryRolesOrderedByPriority() : array
    {
        return $this->queryBuilder->select(['*'])
            ->from('roles')
            ->orderBy('priority', 'DESC')
            ->get();
    }
}
