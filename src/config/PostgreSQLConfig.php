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
 */

declare(strict_types=1);

namespace morfeditorial\config;

use morfeditorial\interfaces\DatabaseConfigInterface;

class PostgreSQLConfig implements DatabaseConfigInterface
{
    public function __construct(
        private string $host,
        private string $dbname,
        private string $username,
        private string $password,
        private string $port = '5432',
        private array $options = []
    ) {}

    public function getDsn() : string
    {
        return "pgsql:host={$this->host};port={$this->port};dbname={$this->dbname}";
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
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
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
