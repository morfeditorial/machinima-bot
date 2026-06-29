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
use App\Entity\User;


class RoleAssignScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        if (str_starts_with($action, 'role:assign')) {
            return true;
        }

        $text = $update['message']['text'] ?? '';
        $userId = $update['message']['from']['id'] ?? 0;
        
        if ($text && $userId) {
            $stateValue = $this->userStateRepo->get($userId, 'awaiting_user_id_for_role');
            if ($stateValue !== null) {
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

        if ('ask_user' === $subAction) {
            $role_name = $payload['params'][1] ?? '';
            $this->userStateRepo->set($userId, ['role_name' => $role_name], 'awaiting_user_id_for_role');

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'view', 'show', $role_name)],
                    ],
                ],
            ];

            $caption = str_replace('{role}', htmlspecialchars($role_name), $this->translate('enter_user_id_for_role_message'));

            $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $caption, $keyboard);

            if (isset($update['callback_query']['id'])) {
                $this->client->answerCallbackQuery($update['callback_query']['id']);
            }
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

        $state_data = $this->userStateRepo->get($userId, 'awaiting_user_id_for_role');
        if ($state_data) {
            $target_user_id = (int) $text;

            if ($target_user_id <= 0) {
                $this->client->sendMessage($chatId, $this->translate('invalid_user_id_message'));
                return;
            }

            $role_name = $state_data['role_name'] ?? '';
            $result = $this->getRoleService()->assignRole($target_user_id, $role_name);

            if ('success' === $result) {
                $this->client->sendMessage($chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('assign_role_message')));
            } elseif ('already_assigned' === $result) {
                $this->client->sendMessage($chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('role_already_assigned_message')));
            } else {
                $this->client->sendMessage($chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('role_assignment_failure_message')));
            }

            $this->userStateRepo->clear($userId, 'awaiting_user_id_for_role');
        }
    }
}
