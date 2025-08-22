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

namespace morfeditorial\services;

use morfeditorial\interfaces\StorageInterface;

class UserStateService
{
    private $queryBuilder;

    public function __construct(private StorageInterface $storage)
    {
        $this->queryBuilder = $storage->getQueryBuilder();
    }

    public function setState(int $userId, mixed $value, string $key = 'default') : void
    {
        $this->queryBuilder->insert('user_states', [
            'user_id' => $userId,
            'key' => $key,
            'value' => $value,
        ])->execute();
    }

    public function getState(int $userId, string $key = 'default') : mixed
    {
        $result = $this->queryBuilder->select(['value'])
            ->from('user_states')
            ->where('user_id', '=', $userId)
            ->andWhere('key', '=', $key)
            ->first();

        return $result['value'] ?? null;
    }

    public function clearState(int $userId, ?string $key = null) : void
    {
        $query = $this->queryBuilder->delete('user_states')
            ->where('user_id', '=', $userId);

        if (null !== $key) {
            $query->andWhere('key', '=', $key);
        }

        $query->execute();
    }
}
