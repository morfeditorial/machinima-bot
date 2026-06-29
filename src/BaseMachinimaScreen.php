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

namespace morfeditorial;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Morfeditorial\TelegramBotBundle\Screen\AbstractScreen as BundleAbstractScreen;
use morfeditorial\contracts\AuthorServiceInterface;
use morfeditorial\contracts\RoleServiceInterface;
use morfeditorial\contracts\UserServiceInterface;
use morfeditorial\contracts\UserStateServiceInterface;

abstract class BaseMachinimaScreen extends BundleAbstractScreen
{
    protected ContainerInterface $container;
    protected Security $security;

    /**
     * Using #[Required] allows Symfony to inject these automatically 
     * without forcing us to redefine constructors in every child screen.
     */
    #[Required]
    public function setDependencies(ContainerInterface $container, Security $security): void
    {
        $this->container = $container;
        $this->security = $security;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getTranslator()
    {
        return $this->container->get('bot_translator');
    }

    public function translate(string $key, array $params = []): string
    {
        return $this->getTranslator()->translate($key, $params);
    }

    public function getAuthorService(): AuthorServiceInterface
    {
        return $this->container->get('author_service');
    }

    public function getUserService(): UserServiceInterface
    {
        return $this->container->get('user_service');
    }

    public function getUserStateService(): UserStateServiceInterface
    {
        return $this->container->get('user_state_service');
    }

    public function getRoleService(): RoleServiceInterface
    {
        return $this->container->get('role_service');
    }

    public function isGranted(string $role_name): bool
    {
        return $this->security->isGranted($role_name);
    }

    public function getVisualsLinks()
    {
        return $this->container->get('visuals_links');
    }

    /**
     * Parse action and parameters from payload domain:action:arg1:arg2
     * Returns ['domain' => 'domain', 'action' => 'action', 'params' => ['arg1', 'arg2']]
     */
    protected function parsePayload(string $payload): array
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

    /**
     * Create formatted payload for buttons
     */
    protected function makePayload(string $domain, string $action, ...$params): string
    {
        $parts = [$domain, $action, ...$params];
        return implode(':', $parts);
    }

    /**
     * Render a panel: edit existing panel message or send a new photo.
     */
    protected function renderPanel(int $chatId, int $userId, string $visual, string $caption, array $keyboard, bool $safe = false): void
    {
        $currentPanel = $this->getUserService()->getCurrentPanel($userId);

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
