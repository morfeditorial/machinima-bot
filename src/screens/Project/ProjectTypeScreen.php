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

namespace morfeditorial\screens\Project;

use morfeditorial\screens\AbstractScreen;

class ProjectTypeScreen extends AbstractScreen
{
    public function render() : void
    {
        if (!$this->isGranted('creator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');

        $payload = $this->data['payload'] ?? '';
        $parsed = $this->parsePayload($payload);
        $type = $parsed['params'][0] ?? null;

        $current_panel = $user_service->getCurrentPanel($this->userId);
        $state_data = $user_state_service->getState($this->userId, 'awaiting_project_description');

        if ($state_data && $type) {
            $user_state_service->setState($this->userId, array_merge($state_data, ['type' => $type]), 'awaiting_project_description');
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'list')],
                    ],
                ],
            ];
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('enter_project_description_message'), $keyboard);
        }
    }
}
