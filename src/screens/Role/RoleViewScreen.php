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

use morfeditorial\screens\AbstractScreen;

class RoleViewScreen extends AbstractScreen
{
    public function render() : void
    {
        if (! $this->isGranted('admin')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $role_service = $this->bot->getContainer()->get('role_service');
        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

        $all_roles = $role_service->getAllRolesSorted();
        $hierarchy = $role_service->getRoleHierarchy();

        $keyboard = ['inline_keyboard' => []];

        foreach ($all_roles as $role) {
            $raw_children = $hierarchy['ROLE_' . $role['role_name']] ?? [];
            $children_names = array_map(fn ($r) => str_replace('ROLE_', '', $r), $raw_children);
            $children_text = ! empty($children_names) ? implode(', ', $children_names) : "\u{2014}";

            $keyboard['inline_keyboard'][] = [
                [
                    'text' => $role['role_name'],
                    'callback_data' => $this->makePayload('role', 'view', 'show', $role['role_name']),
                ],
                [
                    'text' => $children_text,
                    'callback_data' => $this->makePayload('role', 'view', 'show', $role['role_name']),
                ],
            ];
        }

        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'control')],
        ];

        $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('role_hierarchy_message'), $keyboard);
    }

    public function handleCallback(string $action, array $params) : void
    {
        if (! $this->isGranted('admin')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        if ('view' === $action) {
            $subAction = $params[0] ?? '';

            if ('show' === $subAction) {
                $role_service = $this->bot->getContainer()->get('role_service');
                $user_service = $this->bot->getContainer()->get('user_service');
                $visuals_links = $this->bot->getContainer()->get('visuals_links');
                $current_panel = $user_service->getCurrentPanel($this->userId);

                $role_name = $params[1] ?? '';
                $role = $role_service->getRoleByName($role_name);

                if (! $role) {
                    $this->bot->sendMessage($this->chatId, str_replace('{roleName}', htmlspecialchars($role_name), $this->translate('role_not_found_message')));
                    return;
                }

                $parents = $role_service->getParents($role_name);
                $children = $role_service->getChildren($role_name);
                $parents_text = ! empty($parents) ? implode(', ', array_column($parents, 'role_name')) : "\u{2014}";
                $children_text = ! empty($children) ? implode(', ', array_column($children, 'role_name')) : "\u{2014}";

                $users = $role_service->getUsersByRole($role_name);
                $users_count = count($users);

                $users_text = $users_count > 0 ? implode(', ', array_slice($users, 0, 10)) : "\u{2014}";
                if ($users_count > 10) {
                    $users_text .= ' (та ще ' . ($users_count - 10) . ')';
                }

                $message_text = str_replace(
                    ['{role}', '{parents}', '{children}', '{users}'],
                    [$role_name, $parents_text, $children_text, $users_text],
                    $this->translate('role_detail_message')
                );

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('add_parent'), 'callback_data' => $this->makePayload('role', 'create', 'add_parent', $role_name)],
                            ['text' => $this->translate('remove_child'), 'callback_data' => $this->makePayload('role', 'remove', 'select_child', $role_name)],
                        ],
                        [
                            ['text' => $this->translate('delete_this_role'), 'callback_data' => $this->makePayload('role', 'delete', 'confirm', $role_name)],
                        ],
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'view')],
                        ],
                    ],
                ];

                $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $message_text, $keyboard);
            } else {
                $this->render();
            }
        }
    }

    public function handleMessage(string $text) : void {}
}
