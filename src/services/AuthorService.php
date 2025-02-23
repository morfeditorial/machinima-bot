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

use morfeditorial\repositories\AuthorRepository;

class AuthorService
{
    public function __construct(private AuthorRepository $authorRepo) {}

    public function createAuthor(string $name) : int
    {
        return $this->authorRepo->createAuthor($name);
    }

    public function deleteAuthor(int $authorId) : void
    {
        $this->authorRepo->deleteAuthor($authorId);
    }

    public function getAuthorById(int $authorId) : ?array
    {
        return $this->authorRepo->getAuthorById($authorId);
    }

    public function getAllAuthors() : array
    {
        return $this->authorRepo->getAllAuthors();
    }

    public function updateAuthorName(int $authorId, string $name) : void
    {
        $this->authorRepo->updateAuthorName($authorId, $name);
    }

    public function setBiography(int $authorId, string $biography) : void
    {
        $this->authorRepo->setBiography($authorId, $biography);
    }

    public function setChannelLink(int $authorId, string $link) : void
    {
        $this->authorRepo->setChannelLink($authorId, $link);
    }

    public function setPrivate(int $authorId, bool $private = true) : void
    {
        $this->authorRepo->setPrivate($authorId, $private);
    }

    public function isPrivate(int $authorId) : bool
    {
        return $this->authorRepo->isPrivate($authorId);
    }

    public function getAuthorCreationTime(int $authorId) : ?string
    {
        return $this->authorRepo->getAuthorCreationTime($authorId);
    }

    public function countAuthors() : int
    {
        return $this->authorRepo->countAuthors();
    }
}
