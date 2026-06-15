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

namespace morfeditorial\repositories;

class AuthorRepository
{
    private const STATE_PRIVATE = 'private';

    private const STATE_PUBLIC = 'public';

    public function __construct(private StorageInterface $storage) {}

    public function createAuthor(string $name) : int
    {
        $this->storage->execute(
            'INSERT INTO authors (name, state) VALUES (:name, :state)',
            ['name' => trim($name), 'state' => self::STATE_PRIVATE]
        );

        return $this->storage->lastInsertId();
    }

    public function deleteAuthor(int $authorId) : void
    {
        $this->storage->execute(
            'DELETE FROM authors WHERE id = :id',
            ['id' => $authorId]
        );
    }

    public function getAuthorById(int $authorId) : ?array
    {
        $result = $this->storage->query(
            'SELECT * FROM authors WHERE id = :id LIMIT 1',
            ['id' => $authorId]
        );

        return $result[0] ?? null;
    }

    public function getAllAuthors() : array
    {
        return $this->storage->query('SELECT * FROM authors');
    }

    public function getContentByAuthorId(int $authorId) : array
    {
        return $this->storage->query(
            'SELECT title, description FROM content WHERE author_id = :author_id',
            ['author_id' => $authorId]
        );
    }

    public function updateAuthorName(int $authorId, string $name) : void
    {
        $this->storage->execute(
            'UPDATE authors SET name = :name WHERE id = :id',
            ['id' => $authorId, 'name' => trim($name)]
        );
    }

    public function setBiography(int $authorId, string $biography) : void
    {
        $this->storage->execute(
            'UPDATE authors SET biography = :biography WHERE id = :id',
            ['id' => $authorId, 'biography' => trim($biography)]
        );
    }

    public function setChannelLink(int $authorId, string $link) : void
    {
        $this->storage->execute(
            'UPDATE authors SET channel_link = :link WHERE id = :id',
            ['id' => $authorId, 'link' => trim($link)]
        );
    }

    public function setPrivate(int $authorId, bool $private = true) : void
    {
        $state = $private ? self::STATE_PRIVATE : self::STATE_PUBLIC;
        $this->storage->execute(
            'UPDATE authors SET state = :state WHERE id = :id',
            ['id' => $authorId, 'state' => $state]
        );
    }

    public function isPrivate(int $authorId) : bool
    {
        $result = $this->storage->query(
            'SELECT state FROM authors WHERE id = :id LIMIT 1',
            ['id' => $authorId]
        );

        return ($result[0]['state'] ?? null) === self::STATE_PRIVATE;
    }

    public function getAuthorCreationTime(int $authorId) : ?string
    {
        $result = $this->storage->query(
            'SELECT created_at FROM authors WHERE id = :id LIMIT 1',
            ['id' => $authorId]
        );

        return $result[0]['created_at'] ?? null;
    }

    public function countAuthors() : int
    {
        $result = $this->storage->query('SELECT COUNT(*) as count FROM authors');

        return (int) ($result[0]['count'] ?? 0);
    }
}
