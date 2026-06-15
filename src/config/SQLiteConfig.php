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

class SQLiteConfig implements DatabaseConfigInterface
{
    public function __construct(private string $filePath) {}

    public function getDsn() : string
    {
        return "sqlite:///{$this->filePath}";
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
