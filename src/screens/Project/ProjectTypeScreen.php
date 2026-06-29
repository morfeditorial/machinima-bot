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

use morfeditorial\BaseMachinimaScreen;

class ProjectTypeScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        return strpos($action, 'project:set_type') === 0;
    }

    public function handle(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';

        if (!$this->isGranted('ROLE_CREATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_state_service = $this->getUserStateService();
        $user_service = $this->getUserService();
        $visuals_links = $this->getVisualsLinks();

        $parsed = $this->parsePayload($action);
        $type = $parsed['params'][0] ?? null;

        $state_data = $user_state_service->getState($userId, 'awaiting_project_description');

        if ($state_data && $type) {
            $user_state_service->setState($userId, array_merge($state_data, ['type' => $type]), 'awaiting_project_description');
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'list')],
                    ],
                ],
            ];
            $this->renderPanel($chatId, $userId, $visuals_links[1], $this->translate('enter_project_description_message'), $keyboard);
        }
    }
}
