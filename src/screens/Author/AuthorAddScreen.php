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

namespace morfeditorial\screens\Author;

use morfeditorial\screens\AbstractScreen;

class AuthorAddScreen extends AbstractScreen
{
    public function render() : void
    {
        if (!$this->isGranted('moderator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $this->bot->getUserStateService()->setState($this->userId, 'awaiting_author_name_creation');
        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => 'admin:panel'],
                ],
            ],
        ];

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[10],
            $this->translate('add_author_message'),
            $keyboard
        );
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('add' === $action) {
            $this->render();
        }
    }

    public function handleMessage(string $text) : void
    {
        if (!$this->isGranted('moderator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $messageId = $this->data['message_id'];
        $this->bot->deleteMessage($this->chatId, $messageId);

        $userStateService = $this->bot->getUserStateService();
        $userStateService->clearState($this->userId, 'default');

        $authorService = $this->bot->getContainer()->get('author_service');
        $authorId = $authorService->createAuthor($text);
        $authorStatus = $authorService->isPrivate($authorId);

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('change_name'), 'callback_data' => 'author:change_name:' . $authorId],
                    ['text' => ($authorStatus ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'author:set_private:' . $authorId],
                ],
                [
                    ['text' => $this->translate('add_bio'), 'callback_data' => 'author:set_about:' . $authorId],
                    ['text' => $this->translate('add_link'), 'callback_data' => 'author:add_link:' . $authorId],
                ],
                [
                    ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author:delete_confirm:' . $authorId],
                ],
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => 'admin:panel'],
                ],
            ],
        ];

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[2],
            str_replace('{author}', htmlspecialchars($text), $this->translate('author_added_message')),
            $keyboard
        );
    }
}
