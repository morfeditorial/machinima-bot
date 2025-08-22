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
