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

class RoleControlScreen extends AbstractScreen
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

        $user_state_service->clearState($this->userId);
        $current_page = $user_service->getCurrentPage($this->userId);
        if (! is_null($current_page)) {
            $user_service->resetCurrentPage($this->userId);
        }

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
                    ['text' => $this->translate('go_back'), 'callback_data' => 'control_panel'],
                ],
            ],
        ];

        $current_panel = $user_service->getCurrentPanel($this->userId);
        $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('access_control_panel_message'), $keyboard);
    }

    public function handleCallback(string $action, array $params) : void
    {
        $this->render();
    }

    public function handleMessage(string $text) : void {}
}
