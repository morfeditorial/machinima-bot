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

use morfeditorial\interfaces\StorageInterface;

class UserService
{
    private $queryBuilder;

    public function __construct(private StorageInterface $storage)
    {
        $this->queryBuilder = $storage->getQueryBuilder();
    }

    public function setCurrentPanel(int $userId, int $messageId) : void
    {
        $this->queryBuilder->insert('user_data', [
            'user_id' => $userId,
            'current_panel' => $messageId,
        ])->execute();
    }

    public function getCurrentPanel(int $userId) : ?int
    {
        $result = $this->queryBuilder->select(['current_panel'])
            ->from('user_data')
            ->where('user_id', '=', $userId)
            ->first();

        return $result['current_panel'] ?? null;
    }

    public function setCurrentPage(int $userId, string $page) : void
    {
        $this->queryBuilder->update('user_data', [
            'current_page' => $page,
        ])->where('user_id', '=', $userId)
            ->execute();
    }

    public function getCurrentPage(int $userId) : ?string
    {
        $result = $this->queryBuilder->select(['current_page'])
            ->from('user_data')
            ->where('user_id', '=', $userId)
            ->first();

        return $result['current_page'] ?? null;
    }

    public function resetCurrentPage(int $userId) : void
    {
        $this->queryBuilder->update('user_data', [
            'current_page' => null,
        ])->where('user_id', '=', $userId)
            ->execute();
    }

    public function assignRole(int $userId, string $role) : void
    {
        $this->queryBuilder->update('user_data', [
            'role' => $role,
        ])->where('user_id', '=', $userId)
            ->execute();
    }

    public function removeRole(int $userId) : void
    {
        $this->queryBuilder->update('user_data', [
            'role' => null,
        ])->where('user_id', '=', $userId)
            ->execute();
    }

    public function getRole(int $userId) : ?string
    {
        $result = $this->queryBuilder->select(['role'])
            ->from('user_data')
            ->where('user_id', '=', $userId)
            ->first();

        return $result['role'] ?? null;
    }

    public function getUsersCountByRole(string $role) : int
    {
        return $this->queryBuilder->select()
            ->from('user_data')
            ->where('role', '=', $role)
            ->count();
    }
}
