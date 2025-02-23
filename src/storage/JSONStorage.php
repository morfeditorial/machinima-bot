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
