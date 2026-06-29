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

namespace morfeditorial\screens\Role;

use morfeditorial\BaseMachinimaScreen;

class RoleCreateScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        if (str_starts_with($action, 'role:create')) {
            return true;
        }

        $text = $update['message']['text'] ?? '';
        $userId = $update['message']['from']['id'] ?? 0;
        if ($text && $userId) {
            $state = $this->getUserStateService()->getState($userId);
            if ($state === 'awaiting_role_creation') {
                return true;
            }
        }

        return false;
    }

    public function handle(array $update): void
    {
        $action = $update['callback_query']['data'] ?? '';
        if ($action) {
            $this->handleCallback($update);
        } else {
            $this->handleMessage($update);
        }
    }

    private function handleCallback(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';

        if (!$chatId || !$userId) {
            return;
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $payload = $this->parsePayload($action);
        $subAction = $payload['params'][0] ?? '';

        if (empty($subAction)) {
            $this->getUserStateService()->setState($userId, 'awaiting_role_creation');
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'control')],
                    ],
                ],
            ];

            $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('enter_role_name_message'), $keyboard);
        } elseif ('confirm_parent' === $subAction) {
            $parent_name = $payload['params'][1] ?? '';
            $child_name = $payload['params'][2] ?? '';

            $this->getRoleService()->addParentChild($parent_name, $child_name);
            
            if (isset($update['callback_query']['id'])) {
                $this->client->answerCallbackQuery($update['callback_query']['id'], [
                    'text' => str_replace(['{parent}', '{child}'], [$parent_name, $child_name], $this->translate('parent_added_message'))
                ]);
            }

            $parents = $this->getRoleService()->getParents($child_name);
            $children = $this->getRoleService()->getChildren($child_name);
            $parents_text = ! empty($parents) ? implode(', ', array_column($parents, 'role_name')) : "\u{2014}";
            $children_text = ! empty($children) ? implode(', ', array_column($children, 'role_name')) : "\u{2014}";

            $message_text = str_replace(
                ['{role}', '{parents}', '{children}'],
                [$child_name, $parents_text, $children_text],
                $this->translate('role_detail_message')
            );

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('add_parent'), 'callback_data' => $this->makePayload('role', 'create', 'add_parent', $child_name)],
                        ['text' => $this->translate('remove_child'), 'callback_data' => $this->makePayload('role', 'remove', 'select_child', $child_name)],
                    ],
                    [
                        ['text' => $this->translate('assign_role_to_user'), 'callback_data' => $this->makePayload('role', 'assign', 'ask_user', $child_name)],
                    ],
                    [
                        ['text' => $this->translate('delete_this_role'), 'callback_data' => $this->makePayload('role', 'delete', 'confirm', $child_name)],
                    ],
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'view')],
                    ],
                ],
            ];

            $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $message_text, $keyboard);
        } elseif ('add_parent' === $subAction) {
            $role_name = $payload['params'][1] ?? '';
            $all_roles = $this->getRoleService()->getAllRolesSorted();
            $existing_parents = $this->getRoleService()->getParents($role_name);
            $existing_parent_names = array_column($existing_parents, 'role_name');

            $keyboard = ['inline_keyboard' => []];

            foreach ($all_roles as $role) {
                if ($role['role_name'] === $role_name || in_array($role['role_name'], $existing_parent_names, true)) {
                    continue;
                }

                $children = $this->getRoleService()->getChildren($role['role_name']);
                $children_text = ! empty($children) ? implode(', ', array_column($children, 'role_name')) : "\u{2014}";

                $keyboard['inline_keyboard'][] = [
                    [
                        'text' => $role['role_name'],
                        'callback_data' => $this->makePayload('role', 'create', 'confirm_parent', $role['role_name'], $role_name),
                    ],
                    [
                        'text' => $children_text,
                        'callback_data' => $this->makePayload('role', 'create', 'confirm_parent', $role['role_name'], $role_name),
                    ],
                ];
            }

            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'view', 'show', $role_name)],
            ];

            $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], str_replace('{role}', $role_name, $this->translate('select_parent_message')), $keyboard);
        }

        if (isset($update['callback_query']['id']) && 'confirm_parent' !== $subAction) {
            $this->client->answerCallbackQuery($update['callback_query']['id']);
        }
    }

    private function handleMessage(array $update): void
    {
        $chatId = $update['message']['chat']['id'] ?? 0;
        $userId = $update['message']['from']['id'] ?? 0;
        $text = $update['message']['text'] ?? '';

        if (!$chatId || !$userId) {
            return;
        }

        $default_state = $this->getUserStateService()->getState($userId);

        if ('awaiting_role_creation' === $default_state) {
            if ($this->getRoleService()->getRoleByName($text)) {
                $this->client->sendMessage($chatId, $this->translate('role_already_exist_text_message'));
                return;
            }

            $this->getRoleService()->createRole($text);
            $this->getUserStateService()->clearState($userId, 'default');

            $all_roles = $this->getRoleService()->getAllRolesSorted();
            $keyboard = ['inline_keyboard' => []];

            foreach ($all_roles as $role) {
                if ($role['role_name'] === $text) {
                    continue;
                }
                $keyboard['inline_keyboard'][] = [
                    ['text' => $role['role_name'], 'callback_data' => $this->makePayload('role', 'create', 'confirm_parent', $role['role_name'], $text)],
                ];
            }

            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('no_parent'), 'callback_data' => $this->makePayload('role', 'view')],
            ];

            $this->client->sendMessage($chatId, str_replace('{role}', htmlspecialchars($text), $this->translate('role_created_redirect_message')));

            $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], str_replace('{role}', htmlspecialchars($text), $this->translate('select_parent_message')), $keyboard);
        }
    }
}
