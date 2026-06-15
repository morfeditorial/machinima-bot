<?php

declare(strict_types=1);

namespace morfeditorial\security;

use morfeditorial\services\RoleService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class RolePriorityVoter extends Voter
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
        $required_role = $this->role_service->getRoleByName($attribute);

        if (null === $required_role) {
            return false;
        }

        $user = $token->getUser();

        if (! $user instanceof BotUser) {
            return false;
        }

        $max_user_priority = 0;

        foreach ($user->getRoles() as $role_name) {
            $role = $this->role_service->getRoleByName($role_name);

            if (null !== $role && $role['priority'] > $max_user_priority) {
                $max_user_priority = $role['priority'];
            }
        }

        return $max_user_priority >= $required_role['priority'];
    }
}
