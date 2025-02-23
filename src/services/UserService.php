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

use morfeditorial\repositories\UserRepository;

class UserService
{
    public function __construct(private UserRepository $userRepo) {}

    public function setCurrentPanel(int $userId, int $messageId) : void
    {
        $this->userRepo->setCurrentPanel($userId, $messageId);
    }

    public function getCurrentPanel(int $userId) : ?int
    {
        return $this->userRepo->getCurrentPanel($userId);
    }

    public function setCurrentPage(int $userId, string $page) : void
    {
        $this->userRepo->setCurrentPage($userId, $page);
    }

    public function getCurrentPage(int $userId) : ?string
    {
        return $this->userRepo->getCurrentPage($userId);
    }

    public function resetCurrentPage(int $userId) : void
    {
        $this->userRepo->resetCurrentPage($userId);
    }

    public function assignRole(int $userId, string $role) : void
    {
        $this->userRepo->assignRole($userId, $role);
    }

    public function removeRole(int $userId) : void
    {
        $this->userRepo->removeRole($userId);
    }

    public function getRole(int $userId) : ?string
    {
        return $this->userRepo->getRole($userId);
    }

    public function getUsersCountByRole(string $role) : int
    {
        return $this->userRepo->getUsersCountByRole($role);
    }
}
