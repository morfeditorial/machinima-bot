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
namespace morfeditorial\screens\Author;

use morfeditorial\screens\AbstractScreen;

class AuthorProfileScreen extends AbstractScreen
{
    public function render() : void
    {
        if (!$this->isGranted('moderator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $authorId = (int)$this->data['author_id'];
        $authorService = $this->bot->getContainer()->get('author_service');
        $author = $authorService->getAuthorById($authorId);

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        if (false !== $author) {
            $authorStatus = $authorService->isPrivate($authorId);
            $currentPage = $this->bot->getUserService()->getCurrentPage($this->userId);

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('change_name'), 'callback_data' => 'author:change_name:' . $authorId],
                        ['text' => ($authorStatus ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'author:set_private:' . $authorId],
                    ],
                    [
                        ['text' => ($author['biography'] ? $this->translate('change_bio') : $this->translate('add_bio')), 'callback_data' => 'author:set_about:' . $authorId],
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

            $text = str_replace(
                ['{author}', '{biography}', '{link}'],
                [
                    htmlspecialchars($author['name']),
                    ($author['biography'] ? htmlspecialchars($author['biography']) : $this->translate('bio_not_set')),
                    ($author['channel_link'] ? htmlspecialchars($author['channel_link']) : $this->translate('link_not_set'))
                ],
                $this->translate('author_info_message')
            );

            $this->bot->editMediaMessage(
                $this->chatId,
                $currentPanel,
                $visualsLinks[11],
                $text,
                $keyboard
            );
            return;
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => 'admin:panel'],
                ],
            ],
        ];
        $this->bot->editMediaMessage($this->chatId, $currentPanel, $visualsLinks[1], $this->translate('author_not_found_message'), $keyboard);
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('profile' === $action) {
            $this->data['author_id'] = $params[0] ?? 0;
            $this->render();
        }
    }

    public function handleMessage(string $text) : void
    {
        // Не чекаємо тексту
    }
}
