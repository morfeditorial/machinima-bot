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
        $reachable = $role_hierarchy->getReachableRoleNames(array_map(fn ($role) => 'ROLE_' . $role, $user->getRoles()));
        $attribute = 'ROLE_' . $attribute;

        return in_array($attribute, $reachable, true);
    }
}
