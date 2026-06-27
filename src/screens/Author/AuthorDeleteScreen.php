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

class AuthorDeleteScreen extends AbstractScreen
{
    public function render() : void
    {
        if (!$this->isGranted('moderator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $page = (int)($this->data['page'] ?? 1);
        $this->bot->getUserService()->setCurrentPage($this->userId, 'delete_page_' . $page);

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $keyboard = \morfeditorial\utils\KeyboardHelper::generateAuthorsKeyboard(
            $this->bot->getContainer()->get('translator'),
            $this->bot->getContainer()->get('author_service'),
            $page,
            3,
            1,
            'author:to_delete:',
            'author:delete_page:'
        );

        $authors = $this->bot->getContainer()->get('author_service')->getAllAuthors();
        $messageText = empty($authors) ? $this->translate('empty_authors_list_message') : $this->translate('delete_author_message');

        $this->bot->editMediaMessage($this->chatId, $currentPanel, $visualsLinks[1], $messageText, $keyboard);
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('delete' === $action) {
            $this->data['page'] = 1;
            $this->render();
        } elseif ('delete_page' === $action) {
            $this->data['page'] = (int)$params[0];
            $this->render();
        } elseif ('to_delete' === $action) {
            $this->confirmDelete((int)$params[0]);
        } elseif ('delete_confirm' === $action || 'delete_confirmation' === $action) {
            $this->doDelete((int)$params[0]);
        }
    }

    public function handleMessage(string $text) : void
    {
        // Не чекаємо тексту
    }

    private function confirmDelete(int $authorId) : void
    {
        $authorService = $this->bot->getContainer()->get('author_service');
        $author = $authorService->getAuthorById($authorId);

        if (false === $author) {
            $this->bot->sendMessage($this->chatId, $this->translate('author_not_found_message'));
            return;
        }

        $isOwnProfile = (int) $author['telegram_user_id'] === $this->userId;

        if (!$this->isGranted('moderator') && !$isOwnProfile) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $currentPage = $this->bot->getUserService()->getCurrentPage($this->userId);
        $prefix = preg_match("/^delete_page_(\d+)$/", $currentPage ?? 'delete_page_1', $matches) ? 'author:delete_page:' . $matches[1] : 'author:profile:' . $authorId;

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('confirm_delete'), 'callback_data' => 'author:delete_confirm:' . $authorId],
                ],
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => $prefix],
                ],
            ],
        ];

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[1],
            str_replace('{author}', htmlspecialchars($author['name']), $this->translate('confirm_delete_message')),
            $keyboard
        );
    }

    private function doDelete(int $authorId) : void
    {
        $authorService = $this->bot->getContainer()->get('author_service');
        $author = $authorService->getAuthorById($authorId);

        if (false === $author) {
            $this->bot->sendMessage($this->chatId, $this->translate('author_not_found_message'));
            return;
        }

        $isOwnProfile = (int) $author['telegram_user_id'] === $this->userId;

        if (!$this->isGranted('moderator') && !$isOwnProfile) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $authorService->deleteAuthor($authorId);

        $currentPage = $this->bot->getUserService()->getCurrentPage($this->userId) ?? 'admin:panel';
        $backCallback = preg_match("/^delete_page_(\d+)$/", $currentPage, $matches) ? 'author:delete_page:' . $matches[1] : 'admin:panel';

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => $backCallback],
                ],
            ],
        ];

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[1],
            str_replace('{author}', htmlspecialchars($author['name']), $this->translate('author_deleted_message')),
            $keyboard
        );
    }
}
