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

class YAMLStorage extends FileStorage
{
    public function connect() : void
    {
        $this->filePath = 'database.yaml';
    }

    protected function readData() : array
    {
        return yaml_parse_file($this->filePath) ?? [];
    }

    protected function writeData(array $data) : void
    {
        yaml_emit_file($this->filePath, $data);
    }
}
