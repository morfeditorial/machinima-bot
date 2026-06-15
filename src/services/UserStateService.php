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

class UserStateService
{
    private $db;

    public function __construct(private StorageInterface $storage)
    {
        $this->db = $storage->getConnection();
    }

    public function setState(int $userId, mixed $value, string $key = 'default') : void
    {
        $this->db->executeStatement(
            'INSERT INTO user_states (user_id, state_key, state_value) VALUES (?, ?, ?)
             ON CONFLICT(user_id, state_key) DO UPDATE SET state_value = excluded.state_value',
            [$userId, $key, json_encode($value)]
        );
    }

    public function getState(int $userId, string $key = 'default') : mixed
    {
        $result = $this->db->fetchOne(
            'SELECT state_value FROM user_states WHERE user_id = ? AND state_key = ?',
            [$userId, $key]
        );

        return false !== $result ? json_decode($result, true) : null;
    }

    public function clearState(int $userId, ?string $key = null) : void
    {
        if (null !== $key) {
            $this->db->executeStatement(
                'DELETE FROM user_states WHERE user_id = ? AND state_key = ?',
                [$userId, $key]
            );
        } else {
            $this->db->executeStatement(
                'DELETE FROM user_states WHERE user_id = ?',
                [$userId]
            );
        }
    }
}
