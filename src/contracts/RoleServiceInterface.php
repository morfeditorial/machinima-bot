<?php

declare(strict_types=1);

namespace morfeditorial\contracts;

interface RoleServiceInterface
{
    public function createRole(string $role_name): bool;
    public function addParentChild(string $parent_role_name, string $child_role_name): bool;
    public function removeParentChild(string $parent_role_name, string $child_role_name): bool;
    public function getRoleHierarchy(): array;
    public function deleteRole(string $role_name): bool;
    public function assignRole(int $user_id, string $role_name): string;
    public function doesRoleInclude(string $role_name, string $target_role, array $visited = []): bool;
    public function removeUserRole(int $user_id, string $role_name): bool;
    public function getUserRoleNames(int $user_id): array;
    public function getUsersCountByRole(string $role_name): int;
    public function getUsersByRole(string $role_name): array;
    public function getAllRoles(): array;
    public function getAllRolesSorted(): array;
    public function getRoleByName(string $role_name): ?array;
    public function getRolesCount(): int;
    public function getChildren(string $role_name): array;
    public function getParents(string $role_name): array;
}
