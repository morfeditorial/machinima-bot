<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \/       \//       \//       \
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

namespace morfeditorial\screens\Admin;

use morfeditorial\BaseMachinimaScreen;

class ControlPanelScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        return str_starts_with($action, 'admin:panel') || str_starts_with($action, 'admin:create_public_page') || $action === 'panel';
    }

    public function handle(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';
        $text = $update['message']['text'] ?? '';

        if (str_starts_with($action, 'admin:create_public_page')) {
            $authorService = $this->getContainer()->get('author_service');
            $myAuthorProfile = $authorService->getAuthorByTelegramId($userId);
            if (! $myAuthorProfile) {
                // Fetch first name from telegram or just use "Staff #ID"
                $firstName = $update['callback_query']['from']['first_name'] ?? ('Staff #' . $userId);
                $authorService->createAuthor($firstName, $userId);
            }
        }

        // Regardless of action (if it's panel or create_public_page), we render the panel.
        $this->getUserStateService()->clearState($userId);

        $currentPage = $this->getUserService()->getCurrentPage($userId);
        if (!is_null($currentPage)) {
            $this->getUserService()->resetCurrentPage($userId);
        }

        $keyboard = ['inline_keyboard' => []];

        // Базові кнопки для всіх користувачів
        $keyboard['inline_keyboard'][] = [
            ['text' => '👤 ' . $this->translate('list_of_authors'), 'callback_data' => 'author:list:1'],
            ['text' => '📦 ' . $this->translate('manage_projects'), 'callback_data' => 'project:list'],
        ];

        $authorService = $this->getContainer()->get('author_service');
        $myAuthorProfile = $authorService->getAuthorByTelegramId($userId);

        if ($myAuthorProfile) {
            $keyboard['inline_keyboard'][] = [
                ['text' => '📝 ' . $this->translate('public_page'), 'callback_data' => 'author:profile:' . $myAuthorProfile['id']],
            ];
        } else {
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('create_public_page'), 'callback_data' => 'admin:create_public_page'],
            ];
        }

        // Moderator і вище: керування авторами
        if ($this->isGranted('moderator')) {
            $keyboard['inline_keyboard'][] = [
                ['text' => '✍️ ' . $this->translate('add_author'), 'callback_data' => 'author:add'],
                ['text' => '❌ ' . $this->translate('delete_author'), 'callback_data' => 'author:delete'],
            ];
            $keyboard['inline_keyboard'][] = [
                ['text' => '📂 ' . $this->translate('manage_categories'), 'callback_data' => 'category:manage'],
            ];
        }

        // Admin: керування ролями, категоріями тощо
        if ($this->isGranted('admin')) {
            $keyboard['inline_keyboard'][] = [
                ['text' => '🔒 ' . $this->translate('access_control'), 'callback_data' => 'role:control'],
            ];
        }

        $currentPanel = $this->getUserService()->getCurrentPanel($userId);
        $visualsLinks = $this->getVisualsLinks();

        $caption = $this->translate('admin_panel_message');

        if ($currentPanel) {
            $this->client->request('editMessageMedia', [
                'chat_id' => $chatId,
                'message_id' => $currentPanel,
                'media' => ['type' => 'photo', 'media' => $visualsLinks[1], 'caption' => $caption, 'parse_mode' => 'HTML'],
                'reply_markup' => $keyboard
            ]);
        } else {
            $this->client->sendPhoto($chatId, $visualsLinks[1], [
                'caption' => $caption,
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard
            ]);
        }

        // If there's a text message (which shouldn't happen unless we listen for it, but just in case)
        if ($text) {
            $messageId = $update['message']['message_id'] ?? 0;
            if ($messageId > 0) {
                $this->client->request('deleteMessage', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId
                ]);
            }
        }
    }
}
