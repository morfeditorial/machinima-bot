<?php

declare(strict_types=1);

namespace morfeditorial\services;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\Author;

class RoleService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function createRole(string $role_name) : bool
    {
        $existing = $this->em->getRepository(Role::class)->findOneBy(['roleName' => $role_name]);
        if ($existing) {
            return false;
        }

        $role = new Role();
        $role->setRoleName($role_name);
        $this->em->persist($role);
        $this->em->flush();
        return true;
    }

    public function addParentChild(string $parent_role_name, string $child_role_name) : bool
    {
        $parent = $this->em->getRepository(Role::class)->findOneBy(['roleName' => $parent_role_name]);
        $child = $this->em->getRepository(Role::class)->findOneBy(['roleName' => $child_role_name]);

        if (! $parent || ! $child) {
            return false;
        }

        $parent->addChild($child);
        $this->em->flush();
        return true;
    }

    public function removeParentChild(string $parent_role_name, string $child_role_name) : bool
    {
        $parent = $this->em->getRepository(Role::class)->findOneBy(['roleName' => $parent_role_name]);
        $child = $this->em->getRepository(Role::class)->findOneBy(['roleName' => $child_role_name]);

        if (! $parent || ! $child) {
            return false;
        }

        $parent->removeChild($child);
        $this->em->flush();
        return true;
    }

    public function getRoleHierarchy() : array
    {
        $roles = $this->em->getRepository(Role::class)->findAll();
        $hierarchy = [];

        foreach ($roles as $parent) {
            foreach ($parent->getChildren() as $child) {
                $hierarchy['ROLE_' . $parent->getRoleName()][] = 'ROLE_' . $child->getRoleName();
            }
        }

        return $hierarchy;
    }

    public function deleteRole(string $role_name) : bool
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['roleName' => $role_name]);
        if (! $role) {
            return false;
        }

        // Remove from hierarchy (parents and children)
        $allRoles = $this->em->getRepository(Role::class)->findAll();
        foreach ($allRoles as $r) {
            if ($r->getChildren()->contains($role)) {
                $r->removeChild($role);
            }
        }

        // Also users having this role
        $users = $this->em->getRepository(User::class)->findAll();
        foreach ($users as $user) {
            if ($user->getUserRoles()->contains($role)) {
                $user->removeRole($role);
            }
        }

        $this->em->remove($role);
        $this->em->flush();

        return true;
    }

    public function assignRole(int $user_id, string $role_name) : string
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['roleName' => $role_name]);
        if (! $role) {
            return 'role_not_found';
        }

        $user = $this->em->getRepository(User::class)->find($user_id);
        if (! $user) {
            $user = new User();
            $user->setId($user_id);
            $this->em->persist($user);
        }

        if ($user->getUserRoles()->contains($role)) {
            return 'already_assigned';
        }

        $user->addRole($role);
        
        if ($this->doesRoleInclude($role_name, 'creator')) {
            $author = $this->em->getRepository(Author::class)->findOneBy(['telegramUserId' => $user_id]);
            if (!$author) {
                $author = new Author();
                $author->setName('Creator #' . $user_id);
                $author->setState('private');
                $author->setTelegramUserId($user_id);
                $this->em->persist($author);
            }
        }

        $this->em->flush();
        return 'success';
    }

    public function doesRoleInclude(string $role_name, string $target_role, array $visited = []) : bool
    {
        if ($role_name === $target_role) {
            return true;
        }

        if (in_array($role_name, $visited, true)) {
            return false;
        }
        $visited[] = $role_name;

        $children = $this->getChildren($role_name);
        foreach ($children as $child) {
            if ($this->doesRoleInclude($child['role_name'], $target_role, $visited)) {
                return true;
            }
        }

        return false;
    }

    public function removeUserRole(int $user_id, string $role_name) : bool
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['roleName' => $role_name]);
        $user = $this->em->getRepository(User::class)->find($user_id);

        if (! $role || ! $user) {
            return false;
        }

        $user->removeRole($role);
        $this->em->flush();
        return true;
    }

    public function getUserRoleNames(int $user_id) : array
    {
        $user = $this->em->getRepository(User::class)->find($user_id);
        if (!$user) {
            return [];
        }
        return array_map(fn(Role $r) => $r->getRoleName(), $user->getUserRoles()->toArray());
    }

    public function getUsersCountByRole(string $role_name) : int
    {
        return count($this->getUsersByRole($role_name));
    }

    public function getUsersByRole(string $role_name) : array
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['roleName' => $role_name]);
        if (!$role) {
            return [];
        }

        $users = $this->em->getRepository(User::class)->findAll();
        $userIds = [];
        foreach ($users as $user) {
            if ($user->getUserRoles()->contains($role)) {
                $userIds[] = $user->getId();
            }
        }
        return $userIds;
    }

    public function getAllRoles() : array
    {
        $roles = $this->em->getRepository(Role::class)->findAll();
        return array_map(fn(Role $r) => ['id' => $r->getId(), 'role_name' => $r->getRoleName()], $roles);
    }

    public function getAllRolesSorted() : array
    {
        $roles = $this->getAllRoles();

        $depths = [];
        foreach ($roles as $role) {
            $depths[$role['role_name']] = $this->calculateRoleDepth($role['role_name']);
        }

        usort($roles, function ($a, $b) use ($depths) {
            if ($depths[$a['role_name']] === $depths[$b['role_name']]) {
                return strcmp($a['role_name'], $b['role_name']);
            }

            return $depths[$a['role_name']] <=> $depths[$b['role_name']];
        });

        return $roles;
    }

    private function calculateRoleDepth(string $role_name, array $visited = []) : int
    {
        if (in_array($role_name, $visited, true)) {
            return 999;
        }

        $parents = $this->getParents($role_name);
        if (empty($parents)) {
            return 0;
        }

        $visited[] = $role_name;
        $max_depth = 0;
        foreach ($parents as $parent) {
            $max_depth = max($max_depth, $this->calculateRoleDepth($parent['role_name'], $visited));
        }

        return $max_depth + 1;
    }

    public function getRoleByName(string $role_name) : ?array
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['roleName' => $role_name]);
        if (!$role) {
            return null;
        }
        return ['id' => $role->getId(), 'role_name' => $role->getRoleName()];
    }

    public function getRolesCount() : int
    {
        return $this->em->getRepository(Role::class)->count([]);
    }

    public function getChildren(string $role_name) : array
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['roleName' => $role_name]);
        if (! $role) {
            return [];
        }

        return array_map(fn(Role $r) => ['id' => $r->getId(), 'role_name' => $r->getRoleName()], $role->getChildren()->toArray());
    }

    public function getParents(string $role_name) : array
    {
        $child = $this->em->getRepository(Role::class)->findOneBy(['roleName' => $role_name]);
        if (! $child) {
            return [];
        }

        $allRoles = $this->em->getRepository(Role::class)->findAll();
        $parents = [];
        foreach ($allRoles as $r) {
            if ($r->getChildren()->contains($child)) {
                $parents[] = ['id' => $r->getId(), 'role_name' => $r->getRoleName()];
            }
        }
        return $parents;
    }
}
