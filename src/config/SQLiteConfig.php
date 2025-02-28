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

class SQLiteConfig implements DatabaseConfigInterface
{
    public function __construct(private string $filePath) {}

    public function getDsn() : string
    {
        return "sqlite:{$this->filePath}";
    }

    public function getUsername() : string
    {
        return '';
    }

    public function getPassword() : string
    {
        return '';
    }

    public function getOptions() : array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];
    }
}
