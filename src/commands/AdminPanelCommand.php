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

namespace morfeditorial\commands;

use morfeditorial\BaseMachinimaCommand;
use Morfeditorial\TelegramBotBundle\Client\TelegramClient;
use App\Entity\User;
use App\Entity\UserState;
use App\Entity\Author;

class AdminPanelCommand extends BaseMachinimaCommand
{
    public function __construct(TelegramClient $client)
    {
        parent::__construct($client);
        $this->setAliases(['menu', 'admin_panel']);
    }

    public function getCommand(): string
    {
        return 'admin_panel';
    }

    public function getDescriptionKey(): string
    {
        return 'main_menu_command_description';
    }

    public function handle(array $update): void
    {
        $chatId = $update['message']['chat']['id'] ?? 0;
        $userId = $update['message']['from']['id'] ?? 0;
        $messageId = $update['message']['message_id'] ?? 0;

        if (!$chatId || !$userId) return;

        $user = $this->em->find(User::class, $userId);
        if (!$user) {
            $user = new User();
            $user->setId($userId);
            $this->em->persist($user);
        }

        $states = $this->em->getRepository(UserState::class)->findBy(['user' => $user]);
        foreach ($states as $state) {
            $this->em->remove($state);
        }

        $currentPanel = $user->getCurrentPanel();
        if (!is_null($currentPanel)) {
            try {
                $this->client->deleteMessage($chatId, $currentPanel);
            } catch (\Throwable $e) {
            }
        }

        $user->setCurrentPanel($messageId + 1);

        if (!is_null($user->getCurrentPage())) {
            $user->setCurrentPage(null);
        }

        $this->em->flush();

        $keyboard = ['inline_keyboard' => []];

        $keyboard['inline_keyboard'][] = [
            ['text' => '👤 ' . $this->translate('list_of_authors'), 'callback_data' => 'author:list:1'],
            ['text' => '📦 ' . $this->translate('manage_projects'), 'callback_data' => 'project:list'],
        ];

        $myAuthorProfile = $this->em->getRepository(Author::class)->findOneBy(['telegramUserId' => $userId]);

        if ($myAuthorProfile) {
            $keyboard['inline_keyboard'][] = [
                ['text' => '📝 ' . $this->translate('public_page'), 'callback_data' => 'author:profile:' . $myAuthorProfile->getId()],
            ];
        } else {
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('create_public_page'), 'callback_data' => 'admin:create_public_page'],
            ];
        }

        if ($this->isGranted('ROLE_MODERATOR')) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('add_author'), 'callback_data' => 'author:add'],
                ['text' => $this->translate('delete_author'), 'callback_data' => 'author:delete'],
            ];
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('manage_categories'), 'callback_data' => 'category:manage'],
            ];
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('access_control'), 'callback_data' => 'role:control'],
            ];
        }

        $this->client->sendPhoto($chatId, $this->getVisualsLinks()[1], [
            'caption' => $this->translate('admin_panel_message'),
            'reply_markup' => json_encode($keyboard)
        ]);
    }
}
