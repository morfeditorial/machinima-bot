<?php

declare(strict_types=1);

namespace morfeditorial\services;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Notification;
use App\Entity\User;

class NotificationService
{
    private \Doctrine\DBAL\Connection $db;

    public function __construct(private EntityManagerInterface $em)
    {
        $this->db = $em->getConnection();
    }

    public function notify(int $user_id, string $type, int $target_id, string $message) : array
    {
        $user = $this->em->getRepository(User::class)->find($user_id);
        if (!$user) {
            $user = new User();
            $user->setId($user_id);
            $this->em->persist($user);
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTargetId($target_id);
        $notification->setMessage($message);
        
        $this->em->persist($notification);
        $this->em->flush();

        return $this->getNotificationById($notification->getId());
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
        $notification = $this->em->getRepository(Notification::class)->findOneBy(['id' => $id, 'user' => $user_id]);
        if (!$notification) return false;

        $notification->setIsRead(true);
        $this->em->flush();
        return true;
    }

    public function markAllAsRead(int $user_id) : bool
    {
        $user = $this->em->getRepository(User::class)->find($user_id);
        if (!$user) return false;

        $notifications = $this->em->getRepository(Notification::class)->findBy(['user' => $user, 'isRead' => false]);
        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }
        
        if (!empty($notifications)) {
            $this->em->flush();
        }
        
        return true;
    }

    public function getUnreadCount(int $user_id) : int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
            [$user_id]
        );
    }
}
