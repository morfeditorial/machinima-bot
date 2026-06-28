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

class AuthorProfileScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $payload = $this->parsePayload($action);
        
        if ($payload['domain'] === 'author' && in_array($payload['action'], ['profile', 'set_private', 'unlink_telegram'])) {
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

        if ($route === 'profile') {
            $authorId = (int)($params[0] ?? 0);
            $this->renderProfile($chatId, $userId, $authorId);
        } elseif ($route === 'set_private') {
            $authorId = (int)($params[0] ?? 0);
            $authorService = $this->getAuthorService();
            $author = $authorService->getAuthorById($authorId);

            $isOwnProfile = $author && (int) $author['telegram_user_id'] === $userId;

            if (!$this->isGranted('moderator') && !$isOwnProfile) {
                $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
                return;
            }

            if (null !== $author) {
                $isPrivate = $authorService->isPrivate($authorId);
                $authorService->setPrivate($authorId, !$isPrivate);
                $this->renderProfile($chatId, $userId, $authorId);
            }
        } elseif ($route === 'unlink_telegram') {
            if (!$this->isGranted('admin')) {
                return;
            }
            $authorId = (int)($params[0] ?? 0);
            $authorService = $this->getAuthorService();
            $authorService->setTelegramId($authorId, null);
            $this->renderProfile($chatId, $userId, $authorId);
        }
    }

    private function renderProfile(int $chatId, int $userId, int $authorId): void
    {
        $this->getUserStateService()->clearState($userId);

        $authorService = $this->getAuthorService();
        $author = $authorService->getAuthorById($authorId);

        $isOwnProfile = $author && (int) $author['telegram_user_id'] === $userId;

        if (!$this->isGranted('moderator') && !$isOwnProfile) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $currentPanel = $this->getUserService()->getCurrentPanel($userId);
        $visualsLinks = $this->getVisualsLinks();

        if (null !== $author) {
            $authorStatus = $authorService->isPrivate($authorId);
            $currentPage = $this->getUserService()->getCurrentPage($userId);

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('change_name'), 'callback_data' => 'author:change_name:' . $authorId],
                        ['text' => ($authorStatus ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'author:set_private:' . $authorId],
                    ],
                    [
                        ['text' => ($author['biography'] ? $this->translate('change_bio') : $this->translate('add_bio')), 'callback_data' => 'author:edit_bio:' . $authorId],
                        ['text' => ($author['channel_link'] ? $this->translate('change_link') : $this->translate('add_link')), 'callback_data' => 'author:edit_link:' . $authorId],
                    ],
                ],
            ];

            if ($this->isGranted('admin')) {
                if ($author['telegram_user_id']) {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => $this->translate('unlink_telegram'), 'callback_data' => 'author:unlink_telegram:' . $authorId],
                    ];
                } else {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => $this->translate('link_telegram'), 'callback_data' => 'author:link_telegram:' . $authorId],
                    ];
                }
            }

            if ($this->isGranted('moderator') || $isOwnProfile) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author:to_delete:' . $authorId],
                ];
            }

            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => $currentPage ? $currentPage : 'admin:panel'],
            ];

            $text = str_replace(
                ['{author}', '{biography}', '{link}'],
                [
                    htmlspecialchars($author['name']),
                    ($author['biography'] ? htmlspecialchars($author['biography']) : $this->translate('bio_not_set')),
                    ($author['channel_link'] ? htmlspecialchars($author['channel_link']) : $this->translate('link_not_set'))
                ],
                $this->translate('author_info_message')
            );

            if ($currentPanel) {
                $this->client->request('editMessageMedia', [
                    'chat_id' => $chatId,
                    'message_id' => $currentPanel,
                    'media' => ['type' => 'photo', 'media' => $visualsLinks[11], 'caption' => $text, 'parse_mode' => 'HTML'],
                    'reply_markup' => $keyboard
                ]);
            } else {
                $this->client->sendPhoto($chatId, $visualsLinks[11], $text, null, $keyboard);
            }
            return;
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => 'admin:panel'],
                ],
            ],
        ];

        if ($currentPanel) {
            $this->client->request('editMessageMedia', [
                'chat_id' => $chatId,
                'message_id' => $currentPanel,
                'media' => ['type' => 'photo', 'media' => $visualsLinks[1], 'caption' => $this->translate('author_not_found_message'), 'parse_mode' => 'HTML'],
                'reply_markup' => $keyboard
            ]);
        } else {
            $this->client->sendPhoto($chatId, $visualsLinks[1], $this->translate('author_not_found_message'), null, $keyboard);
        }
    }
}
