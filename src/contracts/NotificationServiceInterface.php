<?php

declare(strict_types=1);

namespace morfeditorial\contracts;

interface NotificationServiceInterface
{
    public function notify(int $user_id, string $type, int $target_id, string $message): array;
    public function getNotificationById(int $id): ?array;
    public function getUserNotifications(int $user_id, bool $unreadOnly = false): array;
    public function markAsRead(int $id, int $user_id): bool;
    public function markAllAsRead(int $user_id): bool;
    public function getUnreadCount(int $user_id): int;
}
