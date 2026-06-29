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

namespace Morfeditorial;

use App\Repository\AuthorRepository;
use App\Repository\UserRepository;
use App\Repository\UserStateRepository;
use App\Service\RoleService;
use Doctrine\ORM\EntityManagerInterface;
use Morfeditorial\TelegramBotBundle\Screen\AbstractScreen as BundleAbstractScreen;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class BaseMachinimaScreen extends BundleAbstractScreen
{
    protected ContainerInterface $container;
    protected Security $security;
    protected EntityManagerInterface $em;
    protected UserRepository $userRepo;
    protected UserStateRepository $userStateRepo;
    protected AuthorRepository $authorRepo;

    #[Required]
    public function setDependencies(ContainerInterface $container, Security $security, EntityManagerInterface $em, UserRepository $userRepo, UserStateRepository $userStateRepo, AuthorRepository $authorRepo) : void
    {
        $this->container = $container;
        $this->security = $security;
        $this->em = $em;
        $this->userRepo = $userRepo;
        $this->userStateRepo = $userStateRepo;
        $this->authorRepo = $authorRepo;
    }

    public function getContainer() : ContainerInterface
    {
        return $this->container;
    }

    public function getTranslator()
    {
        return $this->container->get('bot_translator');
    }

    public function translate(string $key, array $params = []) : string
    {
        return $this->getTranslator()->translate($key, $params);
    }

    public function getRoleService() : RoleService
    {
        return $this->container->get(RoleService::class);
    }

    public function isGranted(string $role_name) : bool
    {
        return $this->security->isGranted($role_name);
    }

    public function getVisualsLinks()
    {
        return $this->container->get('visuals_links');
    }

    protected function parsePayload(string $payload) : array
    {
        $parts = explode(':', $payload);
        $domain = array_shift($parts) ?? '';
        $action = array_shift($parts) ?? '';

        return [
            'domain' => $domain,
            'action' => $action,
            'params' => $parts
        ];
    }

    protected function makePayload(string $domain, string $action, ...$params) : string
    {
        $parts = [$domain, $action, ...$params];
        return implode(':', $parts);
    }

    protected function renderPanel(int $chatId, int $userId, string $visual, string $caption, array $keyboard, bool $safe = false) : void
    {
        $currentPanel = $this->userRepo->getCurrentPanel($userId);

        $editParams = [
            'chat_id' => $chatId,
            'message_id' => $currentPanel,
            'media' => [
                'type' => 'photo',
                'media' => $visual,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ],
            'reply_markup' => $keyboard,
        ];

        $sendParams = [
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard,
        ];

        if ($currentPanel) {
            if ($safe) {
                try {
                    $this->client->request('editMessageMedia', $editParams);
                } catch (\Throwable) {
                    $this->client->sendPhoto($chatId, $visual, $sendParams);
                }
            } else {
                $this->client->request('editMessageMedia', $editParams);
            }
        } else {
            $this->client->sendPhoto($chatId, $visual, $sendParams);
        }
    }
}
