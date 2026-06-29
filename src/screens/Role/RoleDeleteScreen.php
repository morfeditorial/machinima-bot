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

class RoleDeleteScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        return str_starts_with($action, 'role:delete');
    }

    public function handle(array $update) : void
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
            $all_roles = $this->getRoleService()->getAllRolesSorted();

            $keyboard = ['inline_keyboard' => []];

            foreach ($all_roles as $role) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => $role['role_name'], 'callback_data' => $this->makePayload('role', 'delete', 'confirm', $role['role_name'])],
                ];
            }

            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'control')],
            ];

            $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('select_role_to_delete_message'), $keyboard);
        } elseif ('confirm' === $subAction) {
            $role_name = $payload['params'][1] ?? '';

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('confirm_yes'), 'callback_data' => $this->makePayload('role', 'delete', 'do_delete', $role_name)],
                        ['text' => $this->translate('confirm_no'), 'callback_data' => $this->makePayload('role', 'view')],
                    ],
                ],
            ];

            $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], str_replace('{role}', htmlspecialchars($role_name), $this->translate('confirm_delete_role_message')), $keyboard);
        } elseif ('do_delete' === $subAction) {
            $role_name = $payload['params'][1] ?? '';

            if ($this->getRoleService()->deleteRole($role_name)) {
                if (isset($update['callback_query']['id'])) {
                    $this->client->answerCallbackQuery($update['callback_query']['id'], [
                        'text' => str_replace('{role}', htmlspecialchars($role_name), $this->translate('role_deleted_message'))
                    ]);
                }

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('create_role'), 'callback_data' => $this->makePayload('role', 'create')],
                            ['text' => $this->translate('delete_role'), 'callback_data' => $this->makePayload('role', 'delete')],
                        ],
                        [
                            ['text' => $this->translate('manage_user_roles'), 'callback_data' => $this->makePayload('role', 'user')],
                            ['text' => $this->translate('view_roles'), 'callback_data' => $this->makePayload('role', 'view')],
                        ],
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => 'admin:panel'],
                        ],
                    ],
                ];

                $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('access_control_panel_message'), $keyboard);
            } else {
                if (isset($update['callback_query']['id'])) {
                    $this->client->answerCallbackQuery($update['callback_query']['id'], [
                        'text' => $this->translate('delete_role_failure_message')
                    ]);
                }
            }
        }

        if (isset($update['callback_query']['id']) && 'do_delete' !== $subAction) {
            $this->client->answerCallbackQuery($update['callback_query']['id']);
        }
    }
}
