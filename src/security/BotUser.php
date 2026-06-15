<?php

declare(strict_types=1);

namespace morfeditorial\security;

use Symfony\Component\Security\Core\User\UserInterface;

class BotUser implements UserInterface
{
    public function __construct(
        private int $user_id,
        /** @var string[] */
        private array $role_names,
    ) {}

    public function getRoles() : array
    {
        return $this->role_names;
    }

    public function getUserIdentifier() : string
    {
        return (string) $this->user_id;
    }

    public function eraseCredentials() : void {}

    public function getUserId() : int
    {
        return $this->user_id;
    }
}
