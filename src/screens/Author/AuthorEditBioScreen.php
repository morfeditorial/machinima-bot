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

class AuthorEditBioScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $payload = $this->parsePayload($action);
        
        if ($payload['domain'] === 'author' && in_array($payload['action'], ['edit_bio', 'set_about'])) {
            return true;
        }

        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $stateCheck = $this->getUserStateService()->getState($userId, 'set_author_about');
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

        if ($payload['domain'] === 'author' && in_array($payload['action'], ['edit_bio', 'set_about'])) {
            $authorId = (int)($payload['params'][0] ?? 0);
            $authorService = $this->getAuthorService();
            $author = $authorService->getAuthorById($authorId);

            $isOwnProfile = $author && (int) $author['telegram_user_id'] === $userId;

            if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }
            $this->getUserStateService()->setState($userId, ['author_id' => $authorId], 'set_author_about');

            $currentPanel = $this->getUserService()->getCurrentPanel($userId);
            $visualsLinks = $this->getVisualsLinks();

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => 'author:profile:' . $authorId],
                    ],
                ],
            ];

            $msgText = $author['biography'] ? $this->translate('pending_bio_change') : $this->translate('pending_bio_add');
            $visual = $author['biography'] ? $visualsLinks[5] : $visualsLinks[4];

            if ($currentPanel) {
                $this->client->request('editMessageMedia', [
                    'chat_id' => $chatId,
                    'message_id' => $currentPanel,
                    'media' => ['type' => 'photo', 'media' => $visual, 'caption' => $msgText, 'parse_mode' => 'HTML'],
                    'reply_markup' => $keyboard
                ]);
            } else {
                $this->client->sendPhoto($chatId, $visual, $msgText, null, $keyboard);
            }
            return;
        }

        if (isset($update['message'])) {
            $messageId = $update['message']['message_id'] ?? 0;
            if ($messageId) {
                $this->client->deleteMessage($chatId, $messageId);
            }

            $userStateService = $this->getUserStateService();
            $state = $userStateService->getState($userId, 'set_author_about');
            $userStateService->clearState($userId, 'set_author_about');

            $authorId = (int)($state['author_id'] ?? 0);
            $authorService = $this->getAuthorService();
            $author = $authorService->getAuthorById($authorId);

            $isOwnProfile = $author && (int) $author['telegram_user_id'] === $userId;

            if (!$this->isGranted('ROLE_MODERATOR') && !$isOwnProfile) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }

            $authorService->setBiography($authorId, $text);
            $authorStatus = $authorService->isPrivate($authorId);

            $currentPage = $this->getUserService()->getCurrentPage($userId);
            $backCallback = $currentPage ? $currentPage : 'admin:panel';

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('change_name'), 'callback_data' => 'author:change_name:' . $authorId],
                        ['text' => ($authorStatus ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'author:set_private:' . $authorId],
                    ],
                    [
                        ['text' => $this->translate('change_bio'), 'callback_data' => 'author:edit_bio:' . $authorId],
                        ['text' => ($author['channel_link'] ? $this->translate('change_link') : $this->translate('add_link')), 'callback_data' => 'author:edit_link:' . $authorId],
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

            $currentPanel = $this->getUserService()->getCurrentPanel($userId);
            $visualsLinks = $this->getVisualsLinks();

            $successText = str_replace(
                ['{author}', '{biography}', '{link}'],
                [
                    htmlspecialchars($author['name']),
                    htmlspecialchars($text),
                    ($author['channel_link'] ? htmlspecialchars($author['channel_link']) : $this->translate('link_not_set'))
                ],
                ($author['biography'] ? $this->translate('bio_changed_message') : $this->translate('bio_added_message'))
            );

            $visual = $author['biography'] ? $visualsLinks[8] : $visualsLinks[7];

            if ($currentPanel) {
                $this->client->request('editMessageMedia', [
                    'chat_id' => $chatId,
                    'message_id' => $currentPanel,
                    'media' => ['type' => 'photo', 'media' => $visual, 'caption' => $successText, 'parse_mode' => 'HTML'],
                    'reply_markup' => $keyboard
                ]);
            } else {
                $this->client->sendPhoto($chatId, $visual, $successText, null, $keyboard);
            }
        }
    }
}
