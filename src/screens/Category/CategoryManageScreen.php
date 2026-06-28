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

class CategoryManageScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        return str_starts_with($action, 'category:manage');
    }

    public function handle(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';

        if (! $this->isGranted('moderator')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $this->getUserStateService()->clearState($userId);

        $payload = $this->parsePayload($action);
        $parentId = isset($payload['params'][0]) && $payload['params'][0] !== '' ? (int) $payload['params'][0] : null;

        $content_service = $this->container->get('content_service');

        $categories = $content_service->getCategoriesByParent($parentId);
        $keyboard = ['inline_keyboard' => []];

        foreach ($categories as $category) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $category['name'], 'callback_data' => $this->makePayload('category', 'manage', (string)$category['id'])],
                ['text' => '❌', 'callback_data' => $this->makePayload('category', 'delete', 'confirm', (string)$category['id'])],
            ];
        }

        if ($parentId) {
            $add_callback = $this->makePayload('category', 'create', 'sub', (string)$parentId);
            $add_text = 'add_subcategory';
            $back_callback = $this->makePayload('category', 'manage');
        } else {
            $add_callback = $this->makePayload('category', 'create', 'main');
            $add_text = 'add_category';
            $back_callback = 'admin:panel';
        }

        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate($add_text), 'callback_data' => $add_callback],
        ];
        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate('go_back'), 'callback_data' => $back_callback],
        ];

        $msg = $this->translate('manage_categories');
        if ($parentId) {
            $parent_category = $content_service->getCategoryById($parentId);
            if ($parent_category) {
                $msg = str_replace('{category}', htmlspecialchars($parent_category['name']), $this->translate('manage_subcategories'));
            }
        }

        $current_panel = $this->getUserService()->getCurrentPanel($userId);

        if ($current_panel) {
            $this->client->request('editMessageMedia', [
                'chat_id' => $chatId,
                'message_id' => $current_panel,
                'media' => ['type' => 'photo', 'media' => $this->getVisualsLinks()[1], 'caption' => $msg, 'parse_mode' => 'HTML'],
                'reply_markup' => $keyboard
            ]);
        } else {
            $this->client->sendPhoto($chatId, $this->getVisualsLinks()[1], $msg, $keyboard);
        }

        if (isset($update['callback_query']['id'])) {
            $this->client->answerCallbackQuery($update['callback_query']['id']);
        }
    }
}
