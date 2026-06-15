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

namespace morfeditorial\storage;

use Doctrine\DBAL\Connection;

interface StorageInterface
{
    public function connect() : void;

    public function getConnection() : Connection;

    public function beginTransaction() : void;

    public function commit() : void;

    public function rollBack() : void;

    public function lastInsertId() : string|int;

    public function query(string $query, array $params = []) : array;

    public function execute(string $query, array $params = []) : void;
}
