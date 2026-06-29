<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \/       \//       \//       \
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

namespace morfeditorial\screens\Role;

use morfeditorial\BaseMachinimaScreen;

class RoleRemoveScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        return str_starts_with($action, 'role:remove');
    }

    public function handle(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';
        $text = $update['message']['text'] ?? '';

        if (! $this->isGranted('ROLE_ADMIN')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $role_service = $this->getRoleService();
        $user_service = $this->getUserService();
        $current_panel = $user_service->getCurrentPanel($userId);
        $visuals_links = $this->getVisualsLinks();

        $parsed = $this->parsePayload($action);
        $subAction = $parsed['params'][0] ?? '';

        if ('select_child' === $subAction) {
            $role_name = $parsed['params'][1] ?? '';
            $children = $role_service->getChildren($role_name);

            $callback_query_id = $update['callback_query']['id'] ?? null;

            if (empty($children)) {
                if ($callback_query_id) {
                    if (method_exists($this->client, 'answerCallbackQuery')) {
                        $this->client->answerCallbackQuery($callback_query_id, $this->translate('no_children_message'));
                    } else {
                        $this->client->request('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'text' => $this->translate('no_children_message'),
                        ]);
                    }
                }
            } else {
                $keyboard = ['inline_keyboard' => []];

                foreach ($children as $child) {
                    $keyboard['inline_keyboard'][] = [
                        [
                            'text' => $child['role_name'],
                            'callback_data' => $this->makePayload('role', 'remove', 'confirm_remove_child', $role_name, $child['role_name']),
                        ],
                    ];
                }

                $keyboard['inline_keyboard'][] = [
                    ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'view', 'show', $role_name)],
                ];

                $caption = str_replace('{role}', $role_name, $this->translate('select_child_to_remove_message'));

                $this->renderPanel($chatId, $userId, $visuals_links[1], $caption, $keyboard);
            }
        } elseif ('confirm_remove_child' === $subAction) {
            $parent_name = $parsed['params'][1] ?? '';
            $child_name = $parsed['params'][2] ?? '';

            $role_service->removeParentChild($parent_name, $child_name);
            $callback_query_id = $update['callback_query']['id'] ?? null;
            if ($callback_query_id) {
                if (method_exists($this->client, 'answerCallbackQuery')) {
                    $this->client->answerCallbackQuery($callback_query_id, str_replace(['{parent}', '{child}'], [$parent_name, $child_name], $this->translate('child_removed_message')));
                } else {
                    $this->client->request('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => str_replace(['{parent}', '{child}'], [$parent_name, $child_name], $this->translate('child_removed_message')),
                    ]);
                }
            }

            // Refresh to parent's detail panel
            $parents = $role_service->getParents($parent_name);
            $children = $role_service->getChildren($parent_name);
            $parents_text = ! empty($parents) ? implode(', ', array_column($parents, 'role_name')) : "\u{2014}";
            $children_text = ! empty($children) ? implode(', ', array_column($children, 'role_name')) : "\u{2014}";

            $message_text = str_replace(
                ['{role}', '{parents}', '{children}'],
                [$parent_name, $parents_text, $children_text],
                $this->translate('role_detail_message')
            );

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('add_parent'), 'callback_data' => $this->makePayload('role', 'create', 'add_parent', $parent_name)],
                        ['text' => $this->translate('remove_child'), 'callback_data' => $this->makePayload('role', 'remove', 'select_child', $parent_name)],
                    ],
                    [
                        ['text' => $this->translate('assign_role_to_user'), 'callback_data' => $this->makePayload('role', 'assign', 'ask_user', $parent_name)],
                    ],
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'view')],
                    ],
                ],
            ];

            $this->renderPanel($chatId, $userId, $visuals_links[1], $message_text, $keyboard);
        }
    }
}
