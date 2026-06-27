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

class RoleUnassignScreen extends AbstractScreen
{
    public function render() : void
    {
        $role_name = $this->data['role_name'] ?? '';
        if (empty($role_name)) {
            return;
        }

        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'view', 'show', $role_name)],
                ],
            ],
        ];

        $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], str_replace('{role}', $role_name, $this->translate('enter_user_id_to_remove_role_message')), $keyboard);
    }

    public function handleCallback(string $action, array $params) : void
    {
        if (! $this->isGranted('admin')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_state_service = $this->bot->getContainer()->get('user_state_service');

        if ('unassign' === $action) {
            $subAction = $params[0] ?? '';
            if ('ask_user' === $subAction) {
                $role_name = $params[1] ?? '';
                $user_state_service->setState($this->userId, ['role_name' => $role_name], 'awaiting_user_id_to_remove_role');

                $this->data['role_name'] = $role_name;
                $this->render();
            }
        }
    }

    public function handleMessage(string $text) : void
    {
        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $state_data = $user_state_service->getState($this->userId, 'awaiting_user_id_to_remove_role');

        if ($state_data) {
            $role_service = $this->bot->getContainer()->get('role_service');
            $target_user_id = (int) $text;

            if ($target_user_id <= 0) {
                $this->bot->sendMessage($this->chatId, $this->translate('invalid_user_id_message'));
                return;
            }

            $role_name = $state_data['role_name'] ?? '';
            $result = $role_service->removeUserRole($target_user_id, $role_name);

            if ($result) {
                $this->bot->sendMessage($this->chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('remove_role_message')));
            } else {
                $this->bot->sendMessage($this->chatId, $this->translate('remove_role_failed_message'));
            }

            $user_state_service->clearState($this->userId, 'awaiting_user_id_to_remove_role');

            // Повернути панель назад до перегляду ролі
            $view_screen = new \morfeditorial\screens\Role\RoleViewScreen($this->bot, $this->data);
            $view_screen->handleCallback('view', ['show', $role_name]);
        }
    }
}
