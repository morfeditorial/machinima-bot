<?php

declare(strict_types=1);

namespace morfeditorial\security;

use morfeditorial\services\RoleService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class RoleHierarchyVoter extends Voter
{
    public function __construct(
        private RoleService $role_service,
    ) {}

    protected function supports(string $attribute, mixed $subject) : bool
    {
        return is_string($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null) : bool
    {
        $user = $token->getUser();

        if (! $user instanceof BotUser) {
            return false;
        }

        $role_hierarchy = new RoleHierarchy($this->role_service->getRoleHierarchy());
        $reachable = $role_hierarchy->getReachableRoleNames($user->getRoles());

        return in_array($attribute, $reachable, true);
    }
}
