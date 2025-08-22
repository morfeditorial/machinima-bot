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
 * Copyright (c) 2024 Sergiy Chernega
 *
 * @author CSSM Group
 * @link https://cssm.pp.ua/
 *
 *
 */

declare(strict_types=1);

namespace morfeditorial\repositories;

class UserRepository
{
    public function __construct(private StorageInterface $storage) {}

    public function setCurrentPanel(int $userId, int $messageId) : void
    {
        $this->storage->execute(
            'INSERT INTO user_data (user_id, current_panel) VALUES (:user_id, :message_id)
             ON DUPLICATE KEY UPDATE current_panel = :message_id',
            ['user_id' => $userId, 'message_id' => $messageId]
        );
    }

    public function getCurrentPanel(int $userId) : ?int
    {
        $result = $this->storage->query(
            'SELECT current_panel FROM user_data WHERE user_id = :user_id LIMIT 1',
            ['user_id' => $userId]
        );

        return $result[0]['current_panel'] ?? null;
    }

    public function setCurrentPage(int $userId, string $page) : void
    {
        $this->storage->execute(
            'UPDATE user_data SET current_page = :page WHERE user_id = :user_id',
            ['user_id' => $userId, 'page' => $page]
        );
    }

    public function getCurrentPage(int $userId) : ?string
    {
        $result = $this->storage->query(
            'SELECT current_page FROM user_data WHERE user_id = :user_id LIMIT 1',
            ['user_id' => $userId]
        );

        return $result[0]['current_page'] ?? null;
    }

    public function resetCurrentPage(int $userId) : void
    {
        $this->storage->execute(
            'UPDATE user_data SET current_page = NULL WHERE user_id = :user_id',
            ['user_id' => $userId]
        );
    }

    public function assignRole(int $userId, string $role) : void
    {
        $this->storage->execute(
            'UPDATE user_data SET role = :role WHERE user_id = :user_id',
            ['user_id' => $userId, 'role' => $role]
        );
    }

    public function removeRole(int $userId) : void
    {
        $this->storage->execute(
            'UPDATE user_data SET role = NULL WHERE user_id = :user_id',
            ['user_id' => $userId]
        );
    }

    public function getRole(int $userId) : ?string
    {
        $result = $this->storage->query(
            'SELECT role FROM user_data WHERE user_id = :user_id LIMIT 1',
            ['user_id' => $userId]
        );

        return $result[0]['role'] ?? null;
    }

    public function getUsersCountByRole(string $role) : int
    {
        $result = $this->storage->query(
            'SELECT COUNT(*) as count FROM user_data WHERE role = :role',
            ['role' => $role]
        );

        return (int) ($result[0]['count'] ?? 0);
    }
}
