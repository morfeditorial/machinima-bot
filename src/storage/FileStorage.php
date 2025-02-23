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

use morfeditorial\interfaces\StorageInterface;
use morfeditorial\processor\QueryProcessor;

abstract class FileStorage implements StorageInterface
{
    protected string $filePath;

    protected array $data = [];

    protected QueryProcessor $queryProcessor;

    public function __construct()
    {
        $this->queryProcessor = new QueryProcessor;
    }

    abstract protected function readData() : array;

    abstract protected function writeData(array $data) : void;

    public function query(string $query, array $params = []) : array
    {
        $this->loadData();

        return $this->queryProcessor->processQuery($this->data, $query, $params);
    }

    public function execute(string $query, array $params = []) : void
    {
        $this->loadData();
        $this->data = $this->queryProcessor->processExecution($this->data, $query, $params);
        $this->writeData($this->data);
    }

    protected function loadData() : void
    {
        $this->data = $this->readData();
    }
}
