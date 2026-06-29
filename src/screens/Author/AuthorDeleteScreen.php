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
use App\Entity\Author;


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
            $this->userRepo->setCurrentPage($userId, 'delete_page_' . $page);

            $visualsLinks = $this->getVisualsLinks();

            $allAuthors = $this->em->getRepository(Author::class)->findAll();
            $keyboard = KeyboardHelper::generateAuthorsKeyboard(
                $this->getTranslator(),
                $allAuthors,
                $page,
                3,
                1,
                'author:to_delete:',
                'author:delete_page:'
            );

            $authors = $allAuthors;
            $messageText = empty($authors) ? $this->translate('empty_authors_list_message') : $this->translate('delete_author_message');

            $this->renderPanel($chatId, $userId, $visualsLinks[1], $messageText, $keyboard);
        } elseif ($route === 'to_delete') {
            $this->confirmDelete($chatId, $userId, (int)($params[0] ?? 0));
        } elseif ($route === 'delete_confirm' || $route === 'delete_confirmation') {
            $this->doDelete($chatId, $userId, (int)($params[0] ?? 0));
        }
    }

    private function confirmDelete(int $chatId, int $userId, int $authorId) : void
    {
        $author = $this->em->find(Author::class, $authorId);

        if (null === $author) {
            $this->client->sendMessage($chatId, $this->translate('author_not_found_message'));
            return;
        }

        $isOwnProfile = (int) $author->getTelegramUserId() === $userId;

        if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $currentPage = $this->userRepo->getCurrentPage($userId);
        $prefix = preg_match("/^delete_page_(\d+)$/", $currentPage ?? 'delete_page_1', $matches) ? 'author:delete_page:' . $matches[1] : 'author:profile:' . $authorId;

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

        $this->renderPanel($chatId, $userId, $visualsLinks[1], str_replace('{author}', htmlspecialchars($author->getName()), $this->translate('confirm_delete_message')), $keyboard);
    }

    private function doDelete(int $chatId, int $userId, int $authorId) : void
    {
        $author = $this->em->find(Author::class, $authorId);

        if (null === $author) {
            $this->client->sendMessage($chatId, $this->translate('author_not_found_message'));
            return;
        }

        $isOwnProfile = (int) $author->getTelegramUserId() === $userId;

        if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $delAuthor = $this->em->find(Author::class, $authorId);
        if ($delAuthor) {
            $this->em->remove($delAuthor);
            $this->em->flush();
        }

        $currentPage = $this->userRepo->getCurrentPage($userId) ?? 'admin:panel';
        $backCallback = preg_match("/^delete_page_(\d+)$/", $currentPage, $matches) ? 'author:delete_page:' . $matches[1] : 'admin:panel';

        $visualsLinks = $this->getVisualsLinks();

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => $backCallback],
                ],
            ],
        ];

        $this->renderPanel($chatId, $userId, $visualsLinks[1], str_replace('{author}', htmlspecialchars($author->getName()), $this->translate('author_deleted_message')), $keyboard);
    }
}
