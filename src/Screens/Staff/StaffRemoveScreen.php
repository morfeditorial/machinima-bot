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

namespace Morfeditorial\MachinimaBotBundle\Screens\Staff;

use Morfeditorial\MachinimaBotBundle\BaseMachinimaScreen;

class StaffRemoveScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        return str_starts_with($action, 'staff:remove');
    }

    public function handle(array $update) : void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';

        if (! $this->isGranted('ROLE_CREATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $payload = $this->parsePayload($action);
        $subAction = $payload['params'][0] ?? '';
        $projectId = isset($payload['params'][1]) ? (int) $payload['params'][1] : 0;
        $authorId = isset($payload['params'][2]) ? (int) $payload['params'][2] : 0;
        $role = isset($payload['params'][3]) ? base64_decode($payload['params'][3]) : '';

        $content_service = $this->container->get('content_service');
        if ($projectId > 0 && !$content_service->canManageProject($userId, $projectId, $this->isGranted('ROLE_MODERATOR'))) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        if ('confirm' === $subAction) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('confirm_yes'), 'callback_data' => $this->makePayload('staff', 'remove', 'execute', (string)$projectId, (string)$authorId, base64_encode($role))],
                    ],
                    [
                        ['text' => $this->translate('confirm_no'), 'callback_data' => $this->makePayload('staff', 'manage', (string)$projectId)],
                    ],
                ],
            ];

            $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('confirm_delete_message'), $keyboard);
        } elseif ('execute' === $subAction) {
            $content_service = $this->container->get('content_service');
            $content_service->removeStaff($projectId, $authorId, $role);

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('staff', 'manage', (string)$projectId)],
                    ],
                ],
            ];

            $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('manage_staff'), $keyboard);
        }

        if (isset($update['callback_query']['id'])) {
            $this->client->answerCallbackQuery($update['callback_query']['id']);
        }
    }
}
