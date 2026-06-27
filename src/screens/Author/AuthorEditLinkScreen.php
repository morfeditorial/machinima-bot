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

namespace morfeditorial\screens\Author;

use morfeditorial\screens\AbstractScreen;

class AuthorEditLinkScreen extends AbstractScreen
{
    public function render() : void
    {
        $authorId = (int)$this->data['author_id'];
        $authorService = $this->bot->getContainer()->get('author_service');
        $author = $authorService->getAuthorById($authorId);

        $isOwnProfile = $author && (int) $author['telegram_user_id'] === $this->userId;

        if (!$this->isGranted('moderator') && !$isOwnProfile) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $this->bot->getUserStateService()->setState($this->userId, ['author_id' => $authorId], 'add_author_link');

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => 'author:profile:' . $authorId],
                ],
            ],
        ];

        $this->bot->editMediaMessage($this->chatId, $currentPanel, $visualsLinks[1], $this->translate('pending_link_change'), $keyboard);
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('edit_link' === $action || 'add_link' === $action) {
            $this->data['author_id'] = $params[0];
            $this->render();
        }
    }

    public function handleMessage(string $text) : void
    {
        $messageId = $this->data['message_id'];
        $this->bot->deleteMessage($this->chatId, $messageId);

        $userStateService = $this->bot->getUserStateService();
        $state = $userStateService->getState($this->userId, 'add_author_link');
        $userStateService->clearState($this->userId, 'add_author_link');

        $authorId = $state['author_id'];
        $authorService = $this->bot->getContainer()->get('author_service');
        $author = $authorService->getAuthorById($authorId);

        $isOwnProfile = $author && (int) $author['telegram_user_id'] === $this->userId;

        if (!$this->isGranted('moderator') && !$isOwnProfile) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $authorService->setChannelLink($authorId, $text);
        $authorStatus = $authorService->isPrivate($authorId);

        $currentPage = $this->bot->getUserService()->getCurrentPage($this->userId);
        $backCallback = $currentPage ? $currentPage : 'admin:panel';

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('change_name'), 'callback_data' => 'author:change_name:' . $authorId],
                    ['text' => ($authorStatus ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'author:set_private:' . $authorId],
                ],
                [
                    ['text' => ($author['biography'] ? $this->translate('change_bio') : $this->translate('add_bio')), 'callback_data' => 'author:edit_bio:' . $authorId],
                    ['text' => $this->translate('change_link'), 'callback_data' => 'author:edit_link:' . $authorId],
                ],
            ],
        ];

        if ($this->isGranted('moderator')) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author:to_delete:' . $authorId],
            ];
        }

        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate('go_back'), 'callback_data' => $backCallback],
        ];

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $successText = str_replace(
            ['{author}', '{biography}', '{link}'],
            [
                htmlspecialchars($author['name']),
                ($author['biography'] ? htmlspecialchars($author['biography']) : $this->translate('bio_not_set')),
                htmlspecialchars($text)
            ],
            $this->translate('link_changed_message')
        );

        $this->bot->editMediaMessage($this->chatId, $currentPanel, $visualsLinks[1], $successText, $keyboard);
    }
}
