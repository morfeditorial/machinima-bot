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

class AuthorLinkTelegramScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $payload = $this->parsePayload($action);
        
        if ($payload['domain'] === 'author' && $payload['action'] === 'link_telegram') {
            return true;
        }

        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $stateCheck = $this->getUserStateService()->getState($userId, 'link_telegram');
        if (isset($update['message']) && is_array($stateCheck) && isset($stateCheck['author_id'])) {
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

        if ($payload['domain'] === 'author' && $payload['action'] === 'link_telegram') {
            $authorId = (int)($payload['params'][0] ?? 0);

            if (!$this->isGranted('ROLE_ADMIN')) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }
            $this->getUserStateService()->setState($userId, ['author_id' => $authorId], 'link_telegram');

            $currentPanel = $this->getUserService()->getCurrentPanel($userId);
            $visualsLinks = $this->getVisualsLinks();

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => 'author:profile:' . $authorId],
                    ],
                ],
            ];

            if ($currentPanel) {
                $this->client->request('editMessageMedia', [
                    'chat_id' => $chatId,
                    'message_id' => $currentPanel,
                    'media' => ['type' => 'photo', 'media' => $visualsLinks[3], 'caption' => $this->translate('pending_telegram_link'), 'parse_mode' => 'HTML'],
                    'reply_markup' => $keyboard
                ]);
            } else {
                $this->client->sendPhoto($chatId, $visualsLinks[3], $this->translate('pending_telegram_link'), null, $keyboard);
            }
            return;
        }

        if (isset($update['message'])) {
            $stateData = $this->getUserStateService()->getState($userId, 'link_telegram');
            $authorId = (int)($stateData['author_id'] ?? 0);

            if (!$this->isGranted('ROLE_ADMIN') || 0 === $authorId) {
                $this->getUserStateService()->clearState($userId, 'link_telegram');
                return;
            }

            $messageId = $update['message']['message_id'] ?? 0;
            if ($messageId) {
                $this->client->deleteMessage($chatId, $messageId);
            }

            $telegramId = (int) trim($text);
            if ($telegramId <= 0) {
                $this->getUserStateService()->clearState($userId, 'link_telegram');
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

            $authorService = $this->getAuthorService();
            $existingAuthor = $authorService->getAuthorByTelegramId($telegramId);
            if (null !== $existingAuthor) {
                $this->client->sendMessage($chatId, $this->translate('telegram_already_linked'));
                $this->getUserStateService()->clearState($userId, 'link_telegram');
                
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

            $authorService->setTelegramId($authorId, $telegramId);
            $this->getUserStateService()->clearState($userId, 'link_telegram');

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
