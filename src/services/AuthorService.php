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

namespace morfeditorial\services;

use morfeditorial\interfaces\StorageInterface;

class AuthorService
{
    private $db;

    public function __construct(private StorageInterface $storage)
    {
        $this->db = $storage->getConnection();
    }

    public function createAuthor(string $name) : int
    {
        $this->db->executeStatement(
            'INSERT INTO authors (name) VALUES (?)',
            [$name]
        );

        return (int) $this->db->lastInsertId();
    }

    public function deleteAuthor(int $authorId) : void
    {
        $this->db->executeStatement(
            'DELETE FROM authors WHERE id = ?',
            [$authorId]
        );
    }

    public function getAuthorById(int $authorId) : ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM authors WHERE id = ?',
            [$authorId]
        );

        return false !== $result ? $result : null;
    }

    public function getAllAuthors() : array
    {
        return $this->db->fetchAllAssociative('SELECT * FROM authors');
    }

    public function updateAuthorName(int $authorId, string $name) : void
    {
        $this->db->executeStatement(
            'UPDATE authors SET name = ? WHERE id = ?',
            [$name, $authorId]
        );
    }

    public function setBiography(int $authorId, string $biography) : void
    {
        $this->db->executeStatement(
            'UPDATE authors SET biography = ? WHERE id = ?',
            [$biography, $authorId]
        );
    }

    public function setChannelLink(int $authorId, string $link) : void
    {
        $this->db->executeStatement(
            'UPDATE authors SET channel_link = ? WHERE id = ?',
            [$link, $authorId]
        );
    }

    public function setPrivate(int $authorId, bool $private = true) : void
    {
        $this->db->executeStatement(
            'UPDATE authors SET private = ? WHERE id = ?',
            [$private, $authorId]
        );
    }

    public function isPrivate(int $authorId) : bool
    {
        $result = $this->db->fetchOne(
            'SELECT private FROM authors WHERE id = ?',
            [$authorId]
        );

        return (bool) $result;
    }

    public function getAuthorCreationTime(int $authorId) : ?string
    {
        $result = $this->db->fetchOne(
            'SELECT created_at FROM authors WHERE id = ?',
            [$authorId]
        );

        return false !== $result ? (string) $result : null;
    }

    public function countAuthors() : int
    {
        return (int) $this->db->fetchOne('SELECT COUNT(*) FROM authors');
    }
}
