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

namespace morfeditorial\screens\Staff;

use morfeditorial\BaseMachinimaScreen;
use App\Entity\User;
use App\Entity\UserState;

class StaffManageScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        return str_starts_with($action, 'staff:manage');
    }

    public function handle(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';

        if (! $this->isGranted('ROLE_CREATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $user = $this->em->find(User::class, $userId);
        if ($user) {
            $states = $this->em->getRepository(UserState::class)->findBy(['user' => $user]);
            foreach ($states as $state) {
                $this->em->remove($state);
            }
            $this->em->flush();
        }

        $payload = $this->parsePayload($action);
        $projectId = isset($payload['params'][0]) ? (int) $payload['params'][0] : 0;

        $content_service = $this->container->get('content_service');
        $staff = $content_service->getStaffByContentId($projectId);

        $keyboard = ['inline_keyboard' => []];
        foreach ($staff as $member) {
            $keyboard['inline_keyboard'][] = [
                ['text' => "❌ " . $member['author_name'] . " (" . $member['role'] . ")", 'callback_data' => $this->makePayload('staff', 'remove', 'confirm', (string)$projectId, (string)$member['author_id'], base64_encode($member['role']))],
            ];
        }

        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate('add_staff_member'), 'callback_data' => $this->makePayload('staff', 'add', 'select_author', (string)$projectId)],
        ];
        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'view', (string)$projectId)],
        ];

        $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('manage_staff'), $keyboard);

        if (isset($update['callback_query']['id'])) {
            $this->client->answerCallbackQuery($update['callback_query']['id']);
        }
    }
}
