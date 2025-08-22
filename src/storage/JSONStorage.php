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

namespace morfeditorial\storage;

class JSONStorage extends FileStorage
{
    public function connect() : void
    {
        $this->filePath = 'database.json';
    }

    protected function readData() : array
    {
        return json_decode(file_get_contents($this->filePath), true) ?? [];
    }

    protected function writeData(array $data) : void
    {
        file_put_contents($this->filePath, json_encode($data));
    }
}
