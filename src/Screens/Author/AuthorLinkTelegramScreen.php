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

namespace Morfeditorial\Screens\Author;

use Morfeditorial\BaseMachinimaScreen;
use Morfeditorial\MachinimaCoreBundle\Entity\Author;

class AuthorLinkTelegramScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $payload = $this->parsePayload($action);

        if ('author' === $payload['domain'] && 'link_telegram' === $payload['action']) {
            return true;
        }

        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $stateCheck = $this->userStateRepo->get($userId, 'link_telegram');
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

        if ('author' === $payload['domain'] && 'link_telegram' === $payload['action']) {
            $authorId = (int)($payload['params'][0] ?? 0);

            if (!$this->isGranted('ROLE_ADMIN')) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }
            $this->userStateRepo->set($userId, ['author_id' => $authorId], 'link_telegram');

            $visualsLinks = $this->getVisualsLinks();

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => 'author:profile:' . $authorId],
                    ],
                ],
            ];

            $this->renderPanel($chatId, $userId, $visualsLinks[3], $this->translate('pending_telegram_link'), $keyboard);
            return;
        }

        if (isset($update['message'])) {
            $stateData = $this->userStateRepo->get($userId, 'link_telegram');
            $authorId = (int)($stateData['author_id'] ?? 0);

            if (!$this->isGranted('ROLE_ADMIN') || 0 === $authorId) {
                $this->userStateRepo->clear($userId, 'link_telegram');
                return;
            }

            $messageId = $update['message']['message_id'] ?? 0;
            if ($messageId) {
                $this->client->deleteMessage($chatId, $messageId);
            }

            $telegramId = (int) trim($text);
            if ($telegramId <= 0) {
                $this->userStateRepo->clear($userId, 'link_telegram');
                // The old code instantiates AuthorProfileScreen and calls render.
                // In new architecture, we might need to simulate an update or redirect.
                // We'll simulate a callback to author:profile
                $profileScreen = new AuthorProfileScreen();
                $profileScreen->setDependencies($this->container, $this->security);
                // We fake the update
                $fakeUpdate = $update;
                $fakeUpdate['callback_query'] = [
                    'from' => ['id' => $userId],
                    'message' => ['chat' => ['id' => $chatId]],
                    'data' => 'author:profile:' . $authorId
                ];
                unset($fakeUpdate['message']);
                $profileScreen->handle($fakeUpdate);
                return;
            }

            $existingAuthor = $this->authorRepo->findByTelegramId($telegramId);
            if (null !== $existingAuthor) {
                $this->client->sendMessage($chatId, $this->translate('telegram_already_linked'));
                $this->userStateRepo->clear($userId, 'link_telegram');

                $profileScreen = new AuthorProfileScreen();
                $profileScreen->setDependencies($this->container, $this->security);
                $fakeUpdate = $update;
                $fakeUpdate['callback_query'] = [
                    'from' => ['id' => $userId],
                    'message' => ['chat' => ['id' => $chatId]],
                    'data' => 'author:profile:' . $authorId
                ];
                unset($fakeUpdate['message']);
                $profileScreen->handle($fakeUpdate);
                return;
            }

            $tgAuthor = $this->em->find(Author::class, $authorId);
            if ($tgAuthor) {
                $tgAuthor->setTelegramUserId($telegramId);
                $this->em->flush();
            }
            $this->userStateRepo->clear($userId, 'link_telegram');

            $profileScreen = new AuthorProfileScreen();
            $profileScreen->setDependencies($this->container, $this->security);
            $fakeUpdate = $update;
            $fakeUpdate['callback_query'] = [
                'from' => ['id' => $userId],
                'message' => ['chat' => ['id' => $chatId]],
                'data' => 'author:profile:' . $authorId
            ];
            unset($fakeUpdate['message']);
            $profileScreen->handle($fakeUpdate);
        }
    }
}
