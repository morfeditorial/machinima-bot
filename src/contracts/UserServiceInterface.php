<?php

declare(strict_types=1);

namespace morfeditorial\contracts;

interface UserServiceInterface
{
    public function setCurrentPanel(int $user_id, int $message_id): void;
    public function getCurrentPanel(int $user_id): ?int;
    public function setCurrentPage(int $user_id, string $page): void;
    public function getCurrentPage(int $user_id): ?string;
    public function resetCurrentPage(int $user_id): void;
}
