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

namespace morfeditorial\services;

use morfeditorial\storage\StorageInterface;

class UserService
{
    private $db;

    public function __construct(private StorageInterface $storage)
    {
        $this->db = $storage->getConnection();
    }

    public function setCurrentPanel(int $userId, int $messageId) : void
    {
        $this->db->executeStatement(
            'INSERT OR REPLACE INTO user_data (user_id, current_panel) VALUES (?, ?)',
            [$userId, $messageId]
        );
    }

    public function getCurrentPanel(int $userId) : ?int
    {
        $result = $this->db->fetchOne(
            'SELECT current_panel FROM user_data WHERE user_id = ?',
            [$userId]
        );

        return false !== $result ? (int) $result : null;
    }

    public function setCurrentPage(int $userId, string $page) : void
    {
        $this->db->executeStatement(
            'UPDATE user_data SET current_page = ? WHERE user_id = ?',
            [$page, $userId]
        );
    }

    public function getCurrentPage(int $userId) : ?string
    {
        $result = $this->db->fetchOne(
            'SELECT current_page FROM user_data WHERE user_id = ?',
            [$userId]
        );

        return false !== $result ? (string) $result : null;
    }

    public function resetCurrentPage(int $userId) : void
    {
        $this->db->executeStatement(
            'UPDATE user_data SET current_page = NULL WHERE user_id = ?',
            [$userId]
        );
    }
}
