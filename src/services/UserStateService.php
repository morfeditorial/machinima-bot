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

use morfeditorial\repositories\UserStateRepository;

class UserStateService
{
    public function __construct(private UserStateRepository $stateRepo) {}

    public function setState(int $userId, mixed $value, string $key = 'default') : void
    {
        $this->stateRepo->setState($userId, $value, $key);
    }

    public function getState(int $userId, string $key = 'default') : mixed
    {
        return $this->stateRepo->getState($userId, $key);
    }

    public function clearState(int $userId, ?string $key = null) : void
    {
        $this->stateRepo->clearState($userId, $key);
    }
}
