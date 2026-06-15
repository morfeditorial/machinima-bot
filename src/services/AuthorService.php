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

namespace morfeditorial\services;

use morfeditorial\storage\StorageInterface;

class AuthorService
{
    private const STATE_PRIVATE = 'private';

    private const STATE_PUBLIC = 'public';

    private $db;

    public function __construct(private StorageInterface $storage)
    {
        $this->db = $storage->getConnection();
    }

    public function createAuthor(string $name) : int
    {
        $this->db->executeStatement(
            'INSERT INTO authors (name, state) VALUES (?, ?)',
            [trim($name), self::STATE_PRIVATE]
        );

        return (int) $this->db->lastInsertId();
    }

    public function deleteAuthor(int $author_id) : void
    {
        $this->db->executeStatement(
            'DELETE FROM authors WHERE id = ?',
            [$author_id]
        );
    }

    public function getAuthorById(int $author_id) : ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM authors WHERE id = ?',
            [$author_id]
        );

        return false !== $result ? $result : null;
    }

    public function getAllAuthors() : array
    {
        return $this->db->fetchAllAssociative('SELECT * FROM authors');
    }

    public function getContentByAuthorId(int $author_id) : array
    {
        return $this->db->fetchAllAssociative(
            'SELECT description FROM content WHERE author_id = ?',
            [$author_id]
        );
    }

    public function updateAuthorName(int $author_id, string $name) : void
    {
        $this->db->executeStatement(
            'UPDATE authors SET name = ? WHERE id = ?',
            [trim($name), $author_id]
        );
    }

    public function setBiography(int $author_id, string $biography) : void
    {
        $this->db->executeStatement(
            'UPDATE authors SET biography = ? WHERE id = ?',
            [trim($biography), $author_id]
        );
    }

    public function setChannelLink(int $author_id, string $link) : void
    {
        $this->db->executeStatement(
            'UPDATE authors SET channel_link = ? WHERE id = ?',
            [trim($link), $author_id]
        );
    }

    public function setPrivate(int $author_id, bool $private = true) : void
    {
        $state = $private ? self::STATE_PRIVATE : self::STATE_PUBLIC;
        $this->db->executeStatement(
            'UPDATE authors SET state = ? WHERE id = ?',
            [$state, $author_id]
        );
    }

    public function isPrivate(int $author_id) : bool
    {
        $result = $this->db->fetchAssociative(
            'SELECT state FROM authors WHERE id = ?',
            [$author_id]
        );

        return $result && self::STATE_PRIVATE === $result['state'];
    }

    public function getAuthorCreationTime(int $author_id) : ?string
    {
        $result = $this->db->fetchOne(
            'SELECT created_at FROM authors WHERE id = ?',
            [$author_id]
        );

        return false !== $result ? (string) $result : null;
    }

    public function countAuthors() : int
    {
        return (int) $this->db->fetchOne('SELECT COUNT(*) FROM authors');
    }
}
