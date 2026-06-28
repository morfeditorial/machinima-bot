<?php

declare(strict_types=1);

namespace morfeditorial\services;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\UserState;
use App\Entity\User;

class UserStateService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function setState(int $user_id, mixed $value, string $key = 'default') : void
    {
        $user = $this->em->getRepository(User::class)->find($user_id);
        if (!$user) {
            $user = new User();
            $user->setId($user_id);
            $this->em->persist($user);
            $this->em->flush();
        }

        // Original code cleared all states first.
        $this->clearState($user_id);

        $state = new UserState();
        $state->setUser($user);
        $state->setStateKey($key);
        $state->setStateValue(json_encode($value));

        $this->em->persist($state);
        $this->em->flush();
    }

    public function getState(int $user_id, string $key = 'default') : mixed
    {
        $user = $this->em->getRepository(User::class)->find($user_id);
        if (!$user) {
            return null;
        }

        $state = $this->em->getRepository(UserState::class)->findOneBy(['user' => $user, 'stateKey' => $key]);
        return $state ? json_decode($state->getStateValue(), true) : null;
    }

    public function clearState(int $user_id, ?string $key = null) : void
    {
        $user = $this->em->getRepository(User::class)->find($user_id);
        if (!$user) {
            return;
        }

        if (null !== $key) {
            $state = $this->em->getRepository(UserState::class)->findOneBy(['user' => $user, 'stateKey' => $key]);
            if ($state) {
                $this->em->remove($state);
            }
        } else {
            $states = $this->em->getRepository(UserState::class)->findBy(['user' => $user]);
            foreach ($states as $state) {
                $this->em->remove($state);
            }
        }
        $this->em->flush();
    }
}
