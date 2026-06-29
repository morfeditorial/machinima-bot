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

namespace Morfeditorial\screens\Author;

use App\Entity\Author;
use Morfeditorial\BaseMachinimaScreen;

class AuthorEditLinkScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $payload = $this->parsePayload($action);

        if ('author' === $payload['domain'] && in_array($payload['action'], ['edit_link', 'add_link'])) {
            return true;
        }

        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $stateCheck = $this->userStateRepo->get($userId, 'add_author_link');
        if (isset($update['message']) && is_array($stateCheck) && isset($stateCheck['author_id'])) {
            return true;
        }

        return false;
    }

    public function handle(array $update) : void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';
        $text = $update['message']['text'] ?? '';

        $payload = $this->parsePayload($action);

        if ('author' === $payload['domain'] && in_array($payload['action'], ['edit_link', 'add_link'])) {
            $authorId = (int)($payload['params'][0] ?? 0);
            $author = $this->em->find(Author::class, $authorId);

            $isOwnProfile = $author && (int) $author->getTelegramUserId() === $userId;

            if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }
            $this->userStateRepo->set($userId, ['author_id' => $authorId], 'add_author_link');

            $visualsLinks = $this->getVisualsLinks();

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => 'author:profile:' . $authorId],
                    ],
                ],
            ];

            $this->renderPanel($chatId, $userId, $visualsLinks[1], $this->translate('pending_link_change'), $keyboard);
            return;
        }

        if (isset($update['message'])) {
            $messageId = $update['message']['message_id'] ?? 0;
            if ($messageId) {
                $this->client->deleteMessage($chatId, $messageId);
            }

            $state = $this->userStateRepo->get($userId, 'add_author_link');
            $this->userStateRepo->clear($userId, 'add_author_link');

            $authorId = (int)($state['author_id'] ?? 0);
            $author = $this->em->find(Author::class, $authorId);

            $isOwnProfile = $author && (int) $author->getTelegramUserId() === $userId;

            if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }

            $linkAuthor = $this->em->find(Author::class, $authorId);
            if ($linkAuthor) {
                $linkAuthor->setChannelLink(trim($text));
                $this->em->flush();
            }
            $authorStatus = 'private' === $this->em->find(Author::class, $authorId)?->getState();

            $currentPage = $this->userRepo->getCurrentPage($userId);
            $backCallback = $currentPage ? $currentPage : 'admin:panel';

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('change_name'), 'callback_data' => 'author:change_name:' . $authorId],
                        ['text' => ($authorStatus ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'author:set_private:' . $authorId],
                    ],
                    [
                        ['text' => ($author->getBiography() ? $this->translate('change_bio') : $this->translate('add_bio')), 'callback_data' => 'author:edit_bio:' . $authorId],
                        ['text' => $this->translate('change_link'), 'callback_data' => 'author:edit_link:' . $authorId],
                    ],
                ],
            ];

            if ($this->isGranted('ROLE_MODERATOR')) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author:to_delete:' . $authorId],
                ];
            }

            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => $backCallback],
            ];

            $visualsLinks = $this->getVisualsLinks();

            $successText = str_replace(
                ['{author}', '{biography}', '{link}'],
                [
                    htmlspecialchars($author->getName()),
                    ($author->getBiography() ? htmlspecialchars($author->getBiography()) : $this->translate('bio_not_set')),
                    htmlspecialchars($text)
                ],
                $this->translate('link_changed_message')
            );

            $this->renderPanel($chatId, $userId, $visualsLinks[1], $successText, $keyboard);
        }
    }
}
