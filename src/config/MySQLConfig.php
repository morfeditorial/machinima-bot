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

namespace morfeditorial\config;

use morfeditorial\interfaces\DatabaseConfigInterface;

class MySQLConfig implements DatabaseConfigInterface
{
    public function __construct(
        private string $host,
        private string $dbname,
        private string $username,
        private string $password,
        private string $port = '3306',
        private array $options = []
    ) {}

    public function getDsn() : string
    {
        return "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";
    }

    public function getUsername() : string
    {
        return $this->username;
    }

    public function getPassword() : string
    {
        return $this->password;
    }

    public function getOptions() : array
    {
        return $this->options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    public function getPort() : string
    {
        return $this->port;
    }

    public function setPort(string $port) : void
    {
        $this->port = $port;
    }
}
