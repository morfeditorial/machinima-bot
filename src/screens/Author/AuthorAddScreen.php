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
use App\Entity\Author;

class AuthorAddScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $payload = $this->parsePayload($action);
        if ($payload['domain'] === 'author' && $payload['action'] === 'add') {
            return true;
        }

        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $state = $this->userStateRepo->get($userId, 'default');
        if (isset($update['message']) && $state === 'awaiting_author_name_creation') {
            return true;
        }

        return false;
    }

    public function handle(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';
        $text = $update['message']['text'] ?? '';

        $payload = $this->parsePayload($action);

        if ($payload['domain'] === 'author' && $payload['action'] === 'add') {
            if (!$this->isGranted('ROLE_MODERATOR')) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }

            $this->userStateRepo->set($userId, 'awaiting_author_name_creation', 'default');
            $visualsLinks = $this->getVisualsLinks();

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => 'admin:panel'],
                    ],
                ],
            ];

            $this->renderPanel($chatId, $userId, $visualsLinks[10], $this->translate('add_author_message'), $keyboard);
            return;
        }

        if (isset($update['message'])) {
            if (!$this->isGranted('ROLE_MODERATOR')) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }

            $messageId = $update['message']['message_id'] ?? 0;
            if ($messageId) {
                $this->client->deleteMessage($chatId, $messageId);
            }

            $this->userStateRepo->clear($userId, 'default');

            $newAuthor = new Author();
            $newAuthor->setName(trim($text));
            $this->em->persist($newAuthor);
            $this->em->flush();
            $authorId = $newAuthor->getId();
            $authorStatus = $this->em->find(Author::class, $authorId)?->getState() === 'private';

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
                ],
            ];

            if ($this->isGranted('ROLE_ADMIN')) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => $this->translate('link_telegram'), 'callback_data' => 'author:link_telegram:' . $authorId],
                ];
            }

            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author:to_delete:' . $authorId],
            ];
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => 'admin:panel'],
            ];

            $visualsLinks = $this->getVisualsLinks();

            $this->renderPanel($chatId, $userId, $visualsLinks[2], str_replace('{author}', htmlspecialchars($text), $this->translate('author_added_message')), $keyboard);
        }
    }
}
