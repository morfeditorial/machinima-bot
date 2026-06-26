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

use morfeditorial\screens\AbstractScreen;

class RoleCreateScreen extends AbstractScreen
{
    public function render() : void
    {
        if (! $this->isGranted('admin')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

        $user_state_service->setState($this->userId, 'awaiting_role_creation');
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'control')],
                ],
            ],
        ];
        $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('enter_role_name_message'), $keyboard);
    }

    public function handleCallback(string $action, array $params) : void
    {
        if (! $this->isGranted('admin')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $role_service = $this->bot->getContainer()->get('role_service');
        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

        if ('confirm_parent' === $action) {
            $parent_name = $params[0] ?? '';
            $child_name = $params[1] ?? '';

            $role_service->addParentChild($parent_name, $child_name);
            $callback_query_id = $this->data['callback_query_id'] ?? null;
            if ($callback_query_id) {
                $this->bot->callbackAnswer($callback_query_id, str_replace(['{parent}', '{child}'], [$parent_name, $child_name], $this->translate('parent_added_message')));
            }

            // Refresh to role detail panel
            $parents = $role_service->getParents($child_name);
            $children = $role_service->getChildren($child_name);
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

            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $message_text, $keyboard);
        } elseif ('add_parent' === $action) {
            $role_name = $params[0] ?? '';
            $all_roles = $role_service->getAllRolesSorted();
            $existing_parents = $role_service->getParents($role_name);
            $existing_parent_names = array_column($existing_parents, 'role_name');

            $keyboard = ['inline_keyboard' => []];

            foreach ($all_roles as $role) {
                if ($role['role_name'] === $role_name || in_array($role['role_name'], $existing_parent_names, true)) {
                    continue;
                }

                $children = $role_service->getChildren($role['role_name']);
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

            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], str_replace('{role}', $role_name, $this->translate('select_parent_message')), $keyboard);
        } else {
            $this->render();
        }
    }

    public function handleMessage(string $text) : void
    {
        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $default_state = $user_state_service->getState($this->userId);

        if ('awaiting_role_creation' === $default_state) {
            $role_service = $this->bot->getContainer()->get('role_service');
            $user_service = $this->bot->getContainer()->get('user_service');
            $visuals_links = $this->bot->getContainer()->get('visuals_links');

            if ($role_service->getRoleByName($text)) {
                $this->bot->sendMessage($this->chatId, $this->translate('role_already_exist_text_message'));
                return;
            }

            $role_service->createRole($text);
            $user_state_service->clearState($this->userId, 'default');

            $all_roles = $role_service->getAllRolesSorted();
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

            $this->bot->sendMessage($this->chatId, str_replace('{role}', $text, $this->translate('role_created_redirect_message')));

            $current_panel = $user_service->getCurrentPanel($this->userId);
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], str_replace('{role}', $text, $this->translate('select_parent_message')), $keyboard);
        }
    }
}
