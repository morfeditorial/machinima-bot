<?php

declare(strict_types=1);

namespace morfeditorial\services;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

class UserService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    private function getUserOrCreate(int $user_id): User
    {
        $user = $this->em->getRepository(User::class)->find($user_id);
        if (!$user) {
            $user = new User();
            $user->setId($user_id);
            $this->em->persist($user);
        }
        return $user;
    }

    public function setCurrentPanel(int $user_id, int $message_id) : void
    {
        $user = $this->getUserOrCreate($user_id);
        $user->setCurrentPanel($message_id);
        $this->em->flush();
    }

    public function getCurrentPanel(int $user_id) : ?int
    {
        $user = $this->em->getRepository(User::class)->find($user_id);
        return $user ? $user->getCurrentPanel() : null;
    }

    public function setCurrentPage(int $user_id, string $page) : void
    {
        $user = $this->getUserOrCreate($user_id);
        $user->setCurrentPage($page);
        $this->em->flush();
    }

    public function getCurrentPage(int $user_id) : ?string
    {
        $user = $this->em->getRepository(User::class)->find($user_id);
        return $user ? $user->getCurrentPage() : null;
    }

    public function resetCurrentPage(int $user_id) : void
    {
        $user = $this->em->getRepository(User::class)->find($user_id);
        if ($user) {
            $user->setCurrentPage(null);
            $this->em->flush();
        }
    }
}
