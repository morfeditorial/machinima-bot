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

use morfeditorial\BaseMachinimaScreen;
use morfeditorial\utils\KeyboardHelper;

class AuthorDeleteScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $payload = $this->parsePayload($action);
        
        if ($payload['domain'] === 'author' && in_array($payload['action'], ['delete', 'delete_page', 'to_delete', 'delete_confirm', 'delete_confirmation'])) {
            return true;
        }

        return false;
    }

    public function handle(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';
        
        $payload = $this->parsePayload($action);
        $route = $payload['action'];
        $params = $payload['params'];

        if ($route === 'delete' || $route === 'delete_page') {
            if (!$this->isGranted('ROLE_MODERATOR')) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }

            $page = $route === 'delete_page' ? (int)($params[0] ?? 1) : 1;
            $this->getUserService()->setCurrentPage($userId, 'delete_page_' . $page);

            $currentPanel = $this->getUserService()->getCurrentPanel($userId);
            $visualsLinks = $this->getVisualsLinks();

            $keyboard = KeyboardHelper::generateAuthorsKeyboard(
                $this->getTranslator(),
                $this->getAuthorService(),
                $page,
                3,
                1,
                'author:to_delete:',
                'author:delete_page:'
            );

            $authors = $this->getAuthorService()->getAllAuthors();
            $messageText = empty($authors) ? $this->translate('empty_authors_list_message') : $this->translate('delete_author_message');

            if ($currentPanel) {
                $this->client->request('editMessageMedia', [
                    'chat_id' => $chatId,
                    'message_id' => $currentPanel,
                    'media' => ['type' => 'photo', 'media' => $visualsLinks[1], 'caption' => $messageText, 'parse_mode' => 'HTML'],
                    'reply_markup' => $keyboard
                ]);
            } else {
                $this->client->sendPhoto($chatId, $visualsLinks[1], $messageText, null, $keyboard);
            }
        } elseif ($route === 'to_delete') {
            $this->confirmDelete($chatId, $userId, (int)($params[0] ?? 0));
        } elseif ($route === 'delete_confirm' || $route === 'delete_confirmation') {
            $this->doDelete($chatId, $userId, (int)($params[0] ?? 0));
        }
    }

    private function confirmDelete(int $chatId, int $userId, int $authorId) : void
    {
        $authorService = $this->getAuthorService();
        $author = $authorService->getAuthorById($authorId);

        if (false === $author) {
            $this->client->sendMessage($chatId, $this->translate('author_not_found_message'));
            return;
        }

        $isOwnProfile = (int) $author['telegram_user_id'] === $userId;

        if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $currentPage = $this->getUserService()->getCurrentPage($userId);
        $prefix = preg_match("/^delete_page_(\d+)$/", $currentPage ?? 'delete_page_1', $matches) ? 'author:delete_page:' . $matches[1] : 'author:profile:' . $authorId;

        $currentPanel = $this->getUserService()->getCurrentPanel($userId);
        $visualsLinks = $this->getVisualsLinks();

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

        if ($currentPanel) {
            $this->client->request('editMessageMedia', [
                'chat_id' => $chatId,
                'message_id' => $currentPanel,
                'media' => ['type' => 'photo', 'media' => $visualsLinks[1], 'caption' => str_replace('{author}', htmlspecialchars($author['name']), $this->translate('confirm_delete_message')), 'parse_mode' => 'HTML'],
                'reply_markup' => $keyboard
            ]);
        } else {
            $this->client->sendPhoto($chatId, $visualsLinks[1], str_replace('{author}', htmlspecialchars($author['name']), $this->translate('confirm_delete_message')), null, $keyboard);
        }
    }

    private function doDelete(int $chatId, int $userId, int $authorId) : void
    {
        $authorService = $this->getAuthorService();
        $author = $authorService->getAuthorById($authorId);

        if (false === $author) {
            $this->client->sendMessage($chatId, $this->translate('author_not_found_message'));
            return;
        }

        $isOwnProfile = (int) $author['telegram_user_id'] === $userId;

        if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $authorService->deleteAuthor($authorId);

        $currentPage = $this->getUserService()->getCurrentPage($userId) ?? 'admin:panel';
        $backCallback = preg_match("/^delete_page_(\d+)$/", $currentPage, $matches) ? 'author:delete_page:' . $matches[1] : 'admin:panel';

        $currentPanel = $this->getUserService()->getCurrentPanel($userId);
        $visualsLinks = $this->getVisualsLinks();

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => $backCallback],
                ],
            ],
        ];

        if ($currentPanel) {
            $this->client->request('editMessageMedia', [
                'chat_id' => $chatId,
                'message_id' => $currentPanel,
                'media' => ['type' => 'photo', 'media' => $visualsLinks[1], 'caption' => str_replace('{author}', htmlspecialchars($author['name']), $this->translate('author_deleted_message')), 'parse_mode' => 'HTML'],
                'reply_markup' => $keyboard
            ]);
        } else {
            $this->client->sendPhoto($chatId, $visualsLinks[1], str_replace('{author}', htmlspecialchars($author['name']), $this->translate('author_deleted_message')), null, $keyboard);
        }
    }
}
