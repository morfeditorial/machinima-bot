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

    public function setCurrentPanel(int $user_id, int $message_id) : void
    {
        $this->db->executeStatement(
            'INSERT OR REPLACE INTO user_data (user_id, current_panel) VALUES (?, ?)',
            [$user_id, $message_id]
        );
    }

    public function getCurrentPanel(int $user_id) : ?int
    {
        $result = $this->db->fetchOne(
            'SELECT current_panel FROM user_data WHERE user_id = ?',
            [$user_id]
        );

        return false !== $result ? (int) $result : null;
    }

    public function setCurrentPage(int $user_id, string $page) : void
    {
        $this->db->executeStatement(
            'UPDATE user_data SET current_page = ? WHERE user_id = ?',
            [$page, $user_id]
        );
    }

    public function getCurrentPage(int $user_id) : ?string
    {
        $result = $this->db->fetchOne(
            'SELECT current_page FROM user_data WHERE user_id = ?',
            [$user_id]
        );

        return false !== $result ? (string) $result : null;
    }

    public function resetCurrentPage(int $user_id) : void
    {
        $this->db->executeStatement(
            'UPDATE user_data SET current_page = NULL WHERE user_id = ?',
            [$user_id]
        );
    }
}
