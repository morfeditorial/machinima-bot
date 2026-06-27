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

class RoleUserManageScreen extends AbstractScreen
{
    public function render() : void
    {
        if (! $this->isGranted('admin')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

        $action = $this->data['action'] ?? 'ask_user';
        $targetUserId = (int) ($this->data['target_user_id'] ?? 0);

        if ('ask_user' === $action) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => 'role:control'],
                    ],
                ],
            ];
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('enter_user_id_for_management_message'), $keyboard);
            return;
        }

        if ($targetUserId <= 0) {
            $this->bot->sendMessage($this->chatId, $this->translate('invalid_user_id_message'));
            return;
        }

        $role_service = $this->bot->getContainer()->get('role_service');
        $userRoles = $role_service->getUserRoleNames($targetUserId);

        if ('detail' === $action) {
            $rolesText = empty($userRoles) ? "\u{2014}" : implode(', ', $userRoles);
            $text = str_replace(['{userId}', '{roles}'], [$targetUserId, $rolesText], $this->translate('user_roles_detail_message'));
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('add_role'), 'callback_data' => $this->makePayload('role', 'user', 'add', (string)$targetUserId)],
                        ['text' => $this->translate('remove_role'), 'callback_data' => $this->makePayload('role', 'user', 'remove', (string)$targetUserId)],
                    ],
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'user')],
                    ],
                ],
            ];
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $text, $keyboard);
            return;
        }

        if ('add' === $action) {
            $allRoles = $role_service->getAllRolesSorted();
            $keyboard = ['inline_keyboard' => []];
            foreach ($allRoles as $role) {
                if (!in_array($role['role_name'], $userRoles, true)) {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => $role['role_name'], 'callback_data' => $this->makePayload('role', 'user', 'do_add', (string)$targetUserId, $role['role_name'])],
                    ];
                }
            }
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'user', 'detail', (string)$targetUserId)],
            ];
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], str_replace('{userId}', (string)$targetUserId, $this->translate('select_role_to_add_message')), $keyboard);
            return;
        }

        if ('remove' === $action) {
            $keyboard = ['inline_keyboard' => []];
            foreach ($userRoles as $role_name) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => $role_name, 'callback_data' => $this->makePayload('role', 'user', 'do_remove', (string)$targetUserId, $role_name)],
                ];
            }
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'user', 'detail', (string)$targetUserId)],
            ];
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], str_replace('{userId}', (string)$targetUserId, $this->translate('select_role_to_remove_message')), $keyboard);
            return;
        }
    }

    public function handleCallback(string $action, array $params) : void
    {
        if (! $this->isGranted('admin')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        if ('user' === $action) {
            $subAction = $params[0] ?? 'ask_user';
            $targetUserId = (int) ($params[1] ?? 0);
            $roleName = $params[2] ?? '';

            if ('ask_user' === $subAction) {
                $this->bot->getContainer()->get('user_state_service')->setState($this->userId, [], 'awaiting_user_id_for_management');
                $this->data['action'] = 'ask_user';
                $this->render();
                return;
            }

            if ('do_add' === $subAction) {
                $result = $this->bot->getContainer()->get('role_service')->assignRole($targetUserId, $roleName);
                if ('success' === $result) {
                    $this->bot->sendMessage($this->chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($roleName), (string)$targetUserId], $this->translate('assign_role_message')));
                } else {
                    $this->bot->sendMessage($this->chatId, $this->translate('role_assignment_failure_message'));
                }
                $subAction = 'detail';
            } elseif ('do_remove' === $subAction) {
                $result = $this->bot->getContainer()->get('role_service')->removeUserRole($targetUserId, $roleName);
                if ($result) {
                    $this->bot->sendMessage($this->chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($roleName), (string)$targetUserId], $this->translate('remove_role_message')));
                } else {
                    $this->bot->sendMessage($this->chatId, $this->translate('remove_role_failed_message'));
                }
                $subAction = 'detail';
            }

            $this->data['action'] = $subAction;
            $this->data['target_user_id'] = $targetUserId;
            $this->render();
        }
    }

    public function handleMessage(string $text) : void
    {
        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $state_data = $user_state_service->getState($this->userId, 'awaiting_user_id_for_management');

        if (false !== $state_data) {
            $targetUserId = (int) $text;

            if ($targetUserId <= 0) {
                $this->bot->sendMessage($this->chatId, $this->translate('invalid_user_id_message'));
                return;
            }

            $messageId = $this->data['message_id'] ?? 0;
            if ($messageId > 0) {
                $this->bot->deleteMessage($this->chatId, $messageId);
            }

            $user_state_service->clearState($this->userId, 'awaiting_user_id_for_management');

            $this->data['action'] = 'detail';
            $this->data['target_user_id'] = $targetUserId;
            $this->render();
        }
    }
}
