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

class AuthorEditNameScreen extends AbstractScreen
{
    public function render() : void
    {
        if (!$this->isGranted('moderator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $authorId = (int)$this->data['author_id'];
        $this->bot->getUserStateService()->setState($this->userId, ['author_id' => $authorId], 'change_name');

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => 'author:profile:' . $authorId],
                ],
            ],
        ];

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[3],
            $this->translate('pending_name_change'),
            $keyboard
        );
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('change_name' === $action) {
            $this->data['author_id'] = $params[0];
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
        $state = $userStateService->getState($this->userId, 'change_name');
        $userStateService->clearState($this->userId, 'change_name');

        $authorId = $state['author_id'];
        $authorService = $this->bot->getContainer()->get('author_service');
        $author = $authorService->getAuthorById($authorId);

        $authorService->updateAuthorName($authorId, $text);
        $authorStatus = $authorService->isPrivate($authorId);

        $currentPage = $this->bot->getUserService()->getCurrentPage($this->userId);

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('change_name'), 'callback_data' => 'author:change_name:' . $authorId],
                    ['text' => ($authorStatus ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'author:set_private:' . $authorId],
                ],
                [
                    ['text' => $this->translate('change_bio'), 'callback_data' => 'author:set_about:' . $authorId],
                    ['text' => ($author['channel_link'] ? $this->translate('change_link') : $this->translate('add_link')), 'callback_data' => 'author:add_link:' . $authorId],
                ],
                [
                    ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author:delete_confirm:' . $authorId],
                ],
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => $currentPage ? 'author:page:' . str_replace('page_', '', $currentPage) : 'admin:panel'],
                ],
            ],
        ];

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $successText = str_replace(
            ['{author}', '{oldName}', '{biography}', '{link}'],
            [
                htmlspecialchars($text),
                htmlspecialchars($author['name']),
                ($author['biography'] ? htmlspecialchars($author['biography']) : $this->translate('bio_not_set')),
                ($author['channel_link'] ? htmlspecialchars($author['channel_link']) : $this->translate('link_not_set'))
            ],
            $this->translate('name_changed_message')
        );

        $this->bot->editMediaMessage($this->chatId, $currentPanel, $visualsLinks[6], $successText, $keyboard);
    }
}
