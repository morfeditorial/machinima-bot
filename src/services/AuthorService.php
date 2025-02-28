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
 *
 */

declare(strict_types=1);

namespace morfeditorial\services;

use morfeditorial\interfaces\StorageInterface;

class AuthorService
{
    private $queryBuilder;

    public function __construct(private StorageInterface $storage)
    {
        $this->queryBuilder = $storage->getQueryBuilder();
    }

    public function createAuthor(string $name) : int
    {
        $this->queryBuilder->insert('authors', [
            'name' => $name,
        ])->execute();

        return $this->queryBuilder->getLastInsertId();
    }

    public function deleteAuthor(int $authorId) : void
    {
        $this->queryBuilder->delete('authors')
            ->where('id', '=', $authorId)
            ->execute();
    }

    public function getAuthorById(int $authorId) : ?array
    {
        return $this->queryBuilder->select(['*'])
            ->from('authors')
            ->where('id', '=', $authorId)
            ->first();
    }

    public function getAllAuthors() : array
    {
        return $this->queryBuilder->select(['*'])
            ->from('authors')
            ->get();
    }

    public function updateAuthorName(int $authorId, string $name) : void
    {
        $this->queryBuilder->update('authors', [
            'name' => $name,
        ])->where('id', '=', $authorId)
            ->execute();
    }

    public function setBiography(int $authorId, string $biography) : void
    {
        $this->queryBuilder->update('authors', [
            'biography' => $biography,
        ])->where('id', '=', $authorId)
            ->execute();
    }

    public function setChannelLink(int $authorId, string $link) : void
    {
        $this->queryBuilder->update('authors', [
            'channel_link' => $link,
        ])->where('id', '=', $authorId)
            ->execute();
    }

    public function setPrivate(int $authorId, bool $private = true) : void
    {
        $this->queryBuilder->update('authors', [
            'private' => $private,
        ])->where('id', '=', $authorId)
            ->execute();
    }

    public function isPrivate(int $authorId) : bool
    {
        $result = $this->queryBuilder->select(['private'])
            ->from('authors')
            ->where('id', '=', $authorId)
            ->first();

        return (bool) $result['private'];
    }

    public function getAuthorCreationTime(int $authorId) : ?string
    {
        $result = $this->queryBuilder->select(['created_at'])
            ->from('authors')
            ->where('id', '=', $authorId)
            ->first();

        return $result['created_at'] ?? null;
    }

    public function countAuthors() : int
    {
        return $this->queryBuilder->select()
            ->from('authors')
            ->count();
    }
}
