<?php

declare(strict_types=1);

namespace morfeditorial\contracts;

interface AuthorServiceInterface
{
    public function createAuthor(string $name, ?int $telegram_user_id = null): int;
    public function deleteAuthor(int $author_id): void;
    public function getTopAuthors(int $limit = 10): array;
    public function getAuthorById(int $author_id): ?array;
    public function getAllAuthors(): array;
    public function getAuthorProjects(int $author_id, int $limit = 10, int $offset = 0): array;
    public function updateAuthorName(int $author_id, string $name): void;
    public function setBiography(int $author_id, string $biography): void;
    public function setChannelLink(int $author_id, string $link): void;
    public function setTelegramId(int $author_id, ?int $telegram_user_id): void;
    public function setPrivate(int $author_id, bool $private = true): void;
    public function isPrivate(int $author_id): bool;
    public function getAuthorCreationTime(int $author_id): ?string;
    public function countAuthors(): int;
    public function getAuthorByTelegramId(int $telegram_user_id): ?array;
}
