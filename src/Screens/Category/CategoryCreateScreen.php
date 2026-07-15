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

namespace Morfeditorial\MachinimaBotBundle\Screens\Category;

use Morfeditorial\MachinimaBotBundle\BaseMachinimaScreen;

class CategoryCreateScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;

        if (str_starts_with($action, 'category:create')) {
            return true;
        }

        if (isset($update['message']['text']) && $this->userStateRepo->get($userId, 'awaiting_category_name')) {
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

        if (! $this->isGranted('ROLE_MODERATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        if (str_starts_with($action, 'category:create')) {
            $payload = $this->parsePayload($action);
            $subAction = $payload['params'][0] ?? '';
            $parentId = isset($payload['params'][1]) ? (int) $payload['params'][1] : null;

            $this->userStateRepo->set($userId, ['parent_id' => $parentId], 'awaiting_category_name');

            $back_callback = $parentId ? $this->makePayload('category', 'manage', (string)$parentId) : $this->makePayload('category', 'manage');

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $back_callback],
                    ],
                ],
            ];

            $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('enter_category_name_message'), $keyboard);

            if (isset($update['callback_query']['id'])) {
                $this->client->answerCallbackQuery($update['callback_query']['id']);
            }
        } elseif ('' !== $text) {
            $state_data = $this->userStateRepo->get($userId, 'awaiting_category_name');

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
                $this->userStateRepo->clear($userId, 'awaiting_category_name');

                $back_callback = $parent_id ? $this->makePayload('category', 'manage', (string)$parent_id) : $this->makePayload('category', 'manage');
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => $back_callback],
                        ],
                    ],
                ];

                $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('category_added_message'), $keyboard);
            }
        }
    }
}
