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

namespace morfeditorial\screens\Admin;

use morfeditorial\screens\AbstractScreen;

class ControlPanelScreen extends AbstractScreen
{
    public function render() : void
    {
        $this->bot->getUserStateService()->clearState($this->userId);

        $currentPage = $this->bot->getUserService()->getCurrentPage($this->userId);
        if (!is_null($currentPage)) {
            $this->bot->getUserService()->resetCurrentPage($this->userId);
        }

        $keyboard = ['inline_keyboard' => []];

        // Базові кнопки для всіх користувачів
        $keyboard['inline_keyboard'][] = [
            ['text' => '👤 ' . $this->translate('list_of_authors'), 'callback_data' => 'author:list:1'],
            ['text' => '📦 ' . $this->translate('manage_projects'), 'callback_data' => 'project:list'],
        ];

        $authorService = $this->bot->getContainer()->get('author_service');
        $myAuthorProfile = $authorService->getAuthorByTelegramId($this->userId);

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

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[1],
            $this->translate('admin_panel_message'),
            $keyboard
        );
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('panel' === $action) {
            $this->render();
            return;
        }

        if ('create_public_page' === $action) {
            $authorService = $this->bot->getContainer()->get('author_service');
            $myAuthorProfile = $authorService->getAuthorByTelegramId($this->userId);
            if (! $myAuthorProfile) {
                // Fetch first name from telegram or just use "Staff #ID"
                $authorName = $this->data['first_name'] ?? ('Staff #' . $this->userId);
                $authorService->createAuthor($authorName, $this->userId);
            }
            $this->render();
            return;
        }
    }

    public function handleMessage(string $text) : void
    {
        // Панель не чекає на текст
        $this->bot->deleteMessage($this->chatId, $this->data['message_id'] ?? 0);
    }
}
