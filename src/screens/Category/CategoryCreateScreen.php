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

namespace morfeditorial\screens\Category;

use morfeditorial\BaseMachinimaScreen;

class CategoryCreateScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;

        if (str_starts_with($action, 'category:create')) {
            return true;
        }

        if (isset($update['message']['text'])) {
            $state = $this->getUserStateService()->getState($userId, 'awaiting_category_name');
            if ($state) {
                return true;
            }
        }

        return false;
    }

    public function handle(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';
        $text = $update['message']['text'] ?? '';

        if (! $this->isGranted('moderator')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        if (str_starts_with($action, 'category:create')) {
            $payload = $this->parsePayload($action);
            $subAction = $payload['params'][0] ?? '';
            $parentId = isset($payload['params'][1]) ? (int) $payload['params'][1] : null;

            $this->getUserStateService()->setState($userId, ['parent_id' => $parentId], 'awaiting_category_name');

            $back_callback = $parentId ? $this->makePayload('category', 'manage', (string)$parentId) : $this->makePayload('category', 'manage');

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $back_callback],
                    ],
                ],
            ];

            $current_panel = $this->getUserService()->getCurrentPanel($userId);

            if ($current_panel) {
                $this->client->request('editMessageMedia', [
                    'chat_id' => $chatId,
                    'message_id' => $current_panel,
                    'media' => ['type' => 'photo', 'media' => $this->getVisualsLinks()[1], 'caption' => $this->translate('enter_category_name_message'), 'parse_mode' => 'HTML'],
                    'reply_markup' => $keyboard
                ]);
            } else {
                $this->client->sendPhoto($chatId, $this->getVisualsLinks()[1], $this->translate('enter_category_name_message'), $keyboard);
            }

            if (isset($update['callback_query']['id'])) {
                $this->client->answerCallbackQuery($update['callback_query']['id']);
            }
        } elseif ($text !== '') {
            $state_data = $this->getUserStateService()->getState($userId, 'awaiting_category_name');

            if ($state_data) {
                $message_id = $update['message']['message_id'] ?? null;
                if ($message_id) {
                    $this->client->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message_id]);
                }

                $content_service = $this->container->get('content_service');
                $parent_id = $state_data['parent_id'] ?? null;
                $name = $text;

                $content_service->createCategory($name, $parent_id);

                $this->client->sendMessage($chatId, str_replace('{name}', htmlspecialchars($name), $this->translate('category_added_message')));
                $this->getUserStateService()->clearState($userId, 'awaiting_category_name');
                
                $back_callback = $parent_id ? $this->makePayload('category', 'manage', (string)$parent_id) : $this->makePayload('category', 'manage');
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => $back_callback],
                        ],
                    ],
                ];

                if (isset($current_panel) && $current_panel) {
                    $this->client->request('editMessageMedia', [
                        'chat_id' => $chatId,
                        'message_id' => $current_panel,
                        'media' => ['type' => 'photo', 'media' => $this->getVisualsLinks()[1], 'caption' => $this->translate('category_added_message'), 'parse_mode' => 'HTML'],
                        'reply_markup' => $keyboard
                    ]);
                } else {
                    $this->client->sendPhoto($chatId, $this->getVisualsLinks()[1], $this->translate('category_added_message'), $keyboard);
                }
            }
        }
    }
}
