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

class AuthorEditNameScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $payload = $this->parsePayload($action);
        
        if ($payload['domain'] === 'author' && $payload['action'] === 'change_name') {
            return true;
        }

        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $state = $this->getUserStateService()->getState($userId);
        if (isset($update['message']) && is_array($state) && ($state['author_id'] ?? false) && ($state['__state'] ?? '') === 'change_name') {
            return true;
        }
        // Actually, if state isn't a keyed array with __state, let's just check the method return value if it's the exact state array or if getstate checks state context. 
        // In original code, it was: `$userStateService->getState($this->userId, 'change_name');` which means it fetched data if state === 'change_name'.
        // The implementation of UserStateService: `getState(int $userId, string $expectedState = null)` returns array or null or string.
        // I'll assume if getState() returns array and it matches or if the current state name matches.
        // Let's use `$this->getUserStateService()->getState($userId, 'change_name') !== null` as the check if that's supported.
        $stateCheck = $this->getUserStateService()->getState($userId, 'change_name');
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

        if ($payload['domain'] === 'author' && $payload['action'] === 'change_name') {
            $authorId = (int)($payload['params'][0] ?? 0);
            $authorService = $this->getAuthorService();
            $author = $authorService->getAuthorById($authorId);

            $isOwnProfile = $author && (int) $author['telegram_user_id'] === $userId;

            if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }
            $this->getUserStateService()->setState($userId, ['author_id' => $authorId], 'change_name');

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

            $userStateService = $this->getUserStateService();
            $state = $userStateService->getState($userId, 'change_name');
            $userStateService->clearState($userId, 'change_name');

            $authorId = (int)($state['author_id'] ?? 0);
            $authorService = $this->getAuthorService();
            $author = $authorService->getAuthorById($authorId);

            $isOwnProfile = $author && (int) $author['telegram_user_id'] === $userId;

            if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }

            $authorService->updateAuthorName($authorId, $text);
            $authorStatus = $authorService->isPrivate($authorId);

            $currentPage = $this->getUserService()->getCurrentPage($userId);

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
                    htmlspecialchars($author['name']),
                    ($author['biography'] ? htmlspecialchars($author['biography']) : $this->translate('bio_not_set')),
                    ($author['channel_link'] ? htmlspecialchars($author['channel_link']) : $this->translate('link_not_set'))
                ],
                $this->translate('name_changed_message')
            );

            $this->renderPanel($chatId, $userId, $visualsLinks[6], $successText, $keyboard);
        }
    }
}
