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

class RoleDeleteScreen extends AbstractScreen
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

        $keyboard = ['inline_keyboard' => []];

        foreach ($all_roles as $role) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $role['role_name'], 'callback_data' => $this->makePayload('role', 'delete', 'confirm', $role['role_name'])],
            ];
        }

        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'control')],
        ];

        $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('select_role_to_delete_message'), $keyboard);
    }

    public function handleCallback(string $action, array $params) : void
    {
        if (! $this->isGranted('admin')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

        if ('delete' === $action) {
            $subAction = $params[0] ?? '';

            if ('confirm' === $subAction) {
                $role_name = $params[1] ?? '';

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('confirm_yes'), 'callback_data' => $this->makePayload('role', 'delete', 'do_delete', $role_name)],
                            ['text' => $this->translate('confirm_no'), 'callback_data' => $this->makePayload('role', 'view')],
                        ],
                    ],
                ];

                $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], str_replace('{role}', $role_name, $this->translate('confirm_delete_role_message')), $keyboard);
            } elseif ('do_delete' === $subAction) {
                $role_service = $this->bot->getContainer()->get('role_service');
                $role_name = $params[1] ?? '';

                $callback_query_id = $this->data['callback_query_id'] ?? null;
                if ($role_service->deleteRole($role_name)) {
                    if ($callback_query_id) {
                        $this->bot->callbackAnswer($callback_query_id, str_replace('{role}', $role_name, $this->translate('role_deleted_message')));
                    }

                    // Refresh to access control panel
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $this->translate('create_role'), 'callback_data' => $this->makePayload('role', 'create')],
                                ['text' => $this->translate('delete_role'), 'callback_data' => $this->makePayload('role', 'delete')],
                            ],
                            [
                                ['text' => $this->translate('view_roles'), 'callback_data' => $this->makePayload('role', 'view')],
                            ],
                            [
                                ['text' => $this->translate('go_back'), 'callback_data' => 'admin:panel'],
                            ],
                        ],
                    ];
                    $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('access_control_panel_message'), $keyboard);
                } else {
                    if ($callback_query_id) {
                        $this->bot->callbackAnswer($callback_query_id, $this->translate('delete_role_failure_message'));
                    }
                }
            } else {
                $this->render();
            }
        }
    }

    public function handleMessage(string $text) : void {}
}
