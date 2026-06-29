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

namespace Morfeditorial\Screens\Role;

use Morfeditorial\BaseMachinimaScreen;

class RoleControlScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        return str_starts_with($action, 'role:control');
    }

    public function handle(array $update) : void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? 0;
        $messageId = $update['callback_query']['message']['message_id'] ?? 0;

        if (!$chatId || !$userId) {
            return;
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $this->userStateRepo->clear($userId);

        $this->userRepo->resetCurrentPage($userId);

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

        // Прибираємо стан "завантаження" з інлайн кнопки
        if (isset($update['callback_query']['id'])) {
            $this->client->answerCallbackQuery($update['callback_query']['id']);
        }
    }
}
