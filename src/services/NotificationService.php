<?php

declare(strict_types=1);

namespace morfeditorial\services;

use Doctrine\DBAL\Connection;
use morfeditorial\storage\StorageInterface;

class NotificationService
{
    private Connection $db;

    public function __construct(private StorageInterface $storage)
    {
        $this->db = $storage->getConnection();
    }

    public function notify(int $user_id, string $type, int $target_id, string $message) : array
    {
        $this->db->executeStatement(
            'INSERT INTO notifications (user_id, type, target_id, message) VALUES (?, ?, ?, ?)',
            [$user_id, $type, $target_id, $message]
        );
        $id = $this->db->lastInsertId();
        
        return $this->getNotificationById((int) $id);
    }

    public function getNotificationById(int $id) : ?array
    {
        $result = $this->db->fetchAssociative('SELECT * FROM notifications WHERE id = ?', [$id]);
        return $result ?: null;
    }

    public function getUserNotifications(int $user_id, bool $unreadOnly = false) : array
    {
        $query = 'SELECT * FROM notifications WHERE user_id = ?';
        if ($unreadOnly) {
            $query .= ' AND is_read = 0';
        }
        $query .= ' ORDER BY created_at DESC LIMIT 50';
        
        return $this->db->fetchAllAssociative($query, [$user_id]);
    }

    public function markAsRead(int $id, int $user_id) : bool
    {
        return (bool) $this->db->executeStatement(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
            [$id, $user_id]
        );
    }

    public function markAllAsRead(int $user_id) : bool
    {
        return (bool) $this->db->executeStatement(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0',
            [$user_id]
        );
    }

    public function getUnreadCount(int $user_id) : int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
            [$user_id]
        );
    }
}
