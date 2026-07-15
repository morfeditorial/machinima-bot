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

namespace Morfeditorial\MachinimaBotBundle\Screens\Author;

use Morfeditorial\MachinimaBotBundle\BaseMachinimaScreen;
use Morfeditorial\MachinimaCoreBundle\Entity\Author;

class AuthorEditNameScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $payload = $this->parsePayload($action);

        if ('author' === $payload['domain'] && 'change_name' === $payload['action']) {
            return true;
        }

        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $state = $this->userStateRepo->get($userId, 'default');
        if (isset($update['message']) && is_array($state) && ($state['author_id'] ?? false) && ($state['__state'] ?? '') === 'change_name') {
            return true;
        }
        $stateCheck = $this->userStateRepo->get($userId, 'change_name');
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

        if ('author' === $payload['domain'] && 'change_name' === $payload['action']) {
            $authorId = (int)($payload['params'][0] ?? 0);
            $author = $this->em->find(Author::class, $authorId);

            $isOwnProfile = $author && (int) $author->getTelegramUserId() === $userId;

            if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }
            $this->userStateRepo->set($userId, ['author_id' => $authorId], 'change_name');

            $visualsLinks = $this->getVisualsLinks();

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => 'author:profile:' . $authorId],
                    ],
                ],
            ];

            $this->renderPanel($chatId, $userId, $visualsLinks[3], $this->translate('pending_name_change'), $keyboard);
            return;
        }

        if (isset($update['message'])) {
            $messageId = $update['message']['message_id'] ?? 0;
            if ($messageId) {
                $this->client->deleteMessage($chatId, $messageId);
            }

            $state = $this->userStateRepo->get($userId, 'change_name');
            $this->userStateRepo->clear($userId, 'change_name');

            $authorId = (int)($state['author_id'] ?? 0);
            $author = $this->em->find(Author::class, $authorId);

            $isOwnProfile = $author && (int) $author->getTelegramUserId() === $userId;

            if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }

            $nameAuthor = $this->em->find(Author::class, $authorId);
            if ($nameAuthor) {
                $nameAuthor->setName(trim($text));
                $this->em->flush();
            }
            $authorStatus = 'private' === $this->em->find(Author::class, $authorId)?->getState();

            $currentPage = $this->userRepo->getCurrentPage($userId);

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('change_name'), 'callback_data' => 'author:change_name:' . $authorId],
                        ['text' => ($authorStatus ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'author:set_private:' . $authorId],
                    ],
                    [
                        ['text' => $this->translate('change_bio'), 'callback_data' => 'author:set_about:' . $authorId],
                        ['text' => ($author->getChannelLink() ? $this->translate('change_link') : $this->translate('add_link')), 'callback_data' => 'author:add_link:' . $authorId],
                    ],
                ],
            ];

            if ($this->isGranted('ROLE_MODERATOR')) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author:to_delete:' . $authorId],
                ];
            }

            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => $currentPage ? $currentPage : 'admin:panel'],
            ];

            $visualsLinks = $this->getVisualsLinks();

            $successText = str_replace(
                ['{author}', '{oldName}', '{biography}', '{link}'],
                [
                    htmlspecialchars($text),
                    htmlspecialchars($author->getName()),
                    ($author->getBiography() ? htmlspecialchars($author->getBiography()) : $this->translate('bio_not_set')),
                    ($author->getChannelLink() ? htmlspecialchars($author->getChannelLink()) : $this->translate('link_not_set'))
                ],
                $this->translate('name_changed_message')
            );

            $this->renderPanel($chatId, $userId, $visualsLinks[6], $successText, $keyboard);
        }
    }
}
