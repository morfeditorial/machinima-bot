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
