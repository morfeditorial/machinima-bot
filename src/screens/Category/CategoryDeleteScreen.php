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

class CategoryDeleteScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        return str_starts_with($action, 'category:delete');
    }

    public function handle(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';

        if (! $this->isGranted('ROLE_MODERATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $payload = $this->parsePayload($action);
        $subAction = $payload['params'][0] ?? '';
        $categoryId = isset($payload['params'][1]) ? (int) $payload['params'][1] : 0;

        if ('confirm' === $subAction) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('confirm_yes'), 'callback_data' => $this->makePayload('category', 'delete', 'execute', (string)$categoryId)],
                    ],
                    [
                        ['text' => $this->translate('confirm_no'), 'callback_data' => $this->makePayload('category', 'manage')],
                    ],
                ],
            ];

            $text = str_replace('{name}', 'ID ' . $categoryId, $this->translate('confirm_delete_category_message'));

            $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $text, $keyboard);
        } elseif ('execute' === $subAction) {
            $this->container->get('content_service')->deleteCategory($categoryId);

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('category', 'manage')],
                    ],
                ],
            ];

            $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('category_deleted_message'), $keyboard);
        }

        if (isset($update['callback_query']['id'])) {
            $this->client->answerCallbackQuery($update['callback_query']['id']);
        }
    }
}
