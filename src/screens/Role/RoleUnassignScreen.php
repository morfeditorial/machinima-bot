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

namespace Morfeditorial\screens\Role;

use Morfeditorial\BaseMachinimaScreen;

class RoleUnassignScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        if (str_starts_with($action, 'role:unassign')) {
            return true;
        }

        $userId = $update['message']['from']['id'] ?? 0;
        if ($userId) {
            $stateValue = $this->userStateRepo->get($userId, 'awaiting_user_id_to_remove_role');
            if (null !== $stateValue) {
                return true;
            }
        }

        return false;
    }

    public function handle(array $update) : void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';
        $text = $update['message']['text'] ?? '';

        if (! $this->isGranted('ROLE_ADMIN')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $visuals_links = $this->getVisualsLinks();
        if ($action && str_starts_with($action, 'role:unassign')) {
            $parsed = $this->parsePayload($action);
            $subAction = $parsed['params'][0] ?? '';

            if ('ask_user' === $subAction) {
                $role_name = $parsed['params'][1] ?? '';
                $this->userStateRepo->set($userId, ['role_name' => $role_name], 'awaiting_user_id_to_remove_role');

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'view', 'show', $role_name)],
                        ],
                    ],
                ];

                $caption = str_replace('{role}', $role_name, $this->translate('enter_user_id_to_remove_role_message'));

                $this->renderPanel($chatId, $userId, $visuals_links[1], $caption, $keyboard);
            }
            return;
        }

        if ($text) {
            $state_data = $this->userStateRepo->get($userId, 'awaiting_user_id_to_remove_role');
            if ($state_data) {
                $role_service = $this->getRoleService();
                $target_user_id = (int) $text;

                if ($target_user_id <= 0) {
                    $this->client->sendMessage($chatId, $this->translate('invalid_user_id_message'));
                    return;
                }

                $role_name = $state_data['role_name'] ?? '';
                $result = $role_service->removeUserRole($target_user_id, $role_name);

                if ($result) {
                    $this->client->sendMessage($chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('remove_role_message')));
                } else {
                    $this->client->sendMessage($chatId, $this->translate('remove_role_failed_message'));
                }

                $this->userStateRepo->clear($userId, 'awaiting_user_id_to_remove_role');

                // Return to view screen
                $viewScreen = new RoleViewScreen();
                $viewScreen->setDependencies($this->container, $this->security);
                $viewScreen->setClient($this->client);
                $fakeUpdate = $update;
                $fakeUpdate['callback_query'] = [
                    'data' => "role:view:show:{$role_name}",
                    'message' => ['chat' => ['id' => $chatId]],
                    'from' => ['id' => $userId]
                ];
                $viewScreen->handle($fakeUpdate);
            }
        }
    }
}
