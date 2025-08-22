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

class UserStateRepository
{
    public function __construct(private StorageInterface $storage) {}

    public function setState(int $userId, mixed $value, string $key = 'default') : void
    {
        $this->storage->execute(
            'INSERT INTO user_states (user_id, state_key, state_value) 
             VALUES (:user_id, :state_key, :state_value) 
             ON CONFLICT(user_id, state_key) DO UPDATE SET state_value = :state_value',
            [
                'user_id' => $userId,
                'state_key' => $key,
                'state_value' => json_encode($value),
            ]
        );
    }

    public function getState(int $userId, string $key = 'default') : mixed
    {
        $result = $this->storage->query(
            'SELECT state_value FROM user_states WHERE user_id = :user_id AND state_key = :state_key',
            ['user_id' => $userId, 'state_key' => $key]
        );

        return $result ? json_decode($result[0]['state_value'], true) : null;
    }

    public function clearState(int $userId, ?string $key = null) : void
    {
        if ($key) {
            $this->storage->execute(
                'DELETE FROM user_states WHERE user_id = :user_id AND state_key = :state_key',
                ['user_id' => $userId, 'state_key' => $key]
            );
        } else {
            $this->storage->execute(
                'DELETE FROM user_states WHERE user_id = :user_id',
                ['user_id' => $userId]
            );
        }
    }
}
