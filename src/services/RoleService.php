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

use morfeditorial\repositories\RoleRepository;

class RoleService
{
    public function __construct(private RoleRepository $roleRepo) {}

    public function createRole(string $roleName, int $priority) : bool
    {
        return $this->roleRepo->createRole($roleName, $priority);
    }

    public function deleteRole(string $roleName) : bool
    {
        return $this->roleRepo->deleteRole($roleName);
    }

    public function getAllRoles() : array
    {
        return $this->roleRepo->getAllRoles();
    }

    public function getRoleByName(string $roleName) : ?array
    {
        return $this->roleRepo->getRoleByName($roleName);
    }

    public function getRolePriority(string $roleName) : int
    {
        return $this->roleRepo->getRolePriority($roleName);
    }

    public function getRolesCount() : int
    {
        return $this->roleRepo->getRolesCount();
    }

    public function updateRolePriority(string $roleName, int $priority) : bool
    {
        return $this->roleRepo->updateRolePriority($roleName, $priority);
    }

    public function updateRolePriorities(string $roleName, int $newPriority) : void
    {
        $this->roleRepo->updateRolePriorities($roleName, $newPriority);
    }

    public function queryRolesOrderedByPriority() : array
    {
        return $this->roleRepo->queryRolesOrderedByPriority();
    }
}
