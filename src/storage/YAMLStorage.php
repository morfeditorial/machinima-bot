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
