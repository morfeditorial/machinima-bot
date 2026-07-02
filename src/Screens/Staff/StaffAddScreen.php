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

namespace Morfeditorial\Screens\Staff;

use App\Entity\Author;
use Morfeditorial\BaseMachinimaScreen;

class StaffAddScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;

        if (str_starts_with($action, 'staff:add')) {
            return true;
        }

        if (isset($update['message']['text'])) {
            $tempUser = $this->userRepo->find($userId);
            $tempState = $tempUser ? $this->userStateRepo->get($userId, 'awaiting_staff_role') : null;
            if ($tempState) {
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

        if (! $this->isGranted('ROLE_CREATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $projectId = 0;
        $state_data = null;
        $payload = [];
        if (str_starts_with($action, 'staff:add')) {
            $payload = $this->parsePayload($action);
            $projectId = isset($payload['params'][1]) ? (int) $payload['params'][1] : 0;
        } elseif ('' !== $text) {
            $state_data = $this->userStateRepo->get($userId, 'awaiting_staff_role');
            if ($state_data) {
                $projectId = (int)$state_data['project_id'];
            }
        }

        $content_service = $this->container->get('content_service');
        if ($projectId > 0 && !$content_service->canManageProject($userId, $projectId, $this->isGranted('ROLE_MODERATOR'))) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        if (str_starts_with($action, 'staff:add')) {
            $subAction = $payload['params'][0] ?? '';

            if ('select_author' === $subAction) {
                $page = 1;
                if (isset($payload['params'][2]) && 'page' === $payload['params'][2]) {
                    $page = isset($payload['params'][3]) ? (int) $payload['params'][3] : 1;
                }

                $allAuthors = $this->em->getRepository(Author::class)->findAll();
                $keyboard = \Morfeditorial\Utils\KeyboardHelper::generateAuthorsKeyboard(
                    $this->getTranslator(),
                    $allAuthors,
                    $page,
                    3,
                    1,
                    "staff:add:select_role:{$projectId}:",
                    "staff:add:select_author:{$projectId}:page:"
                );

                // Replace the default go_back from generateAuthorsKeyboard
                array_pop($keyboard['inline_keyboard']);
                $keyboard['inline_keyboard'][] = [['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('staff', 'manage', (string)$projectId)]];

                $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('select_author_for_staff_message'), $keyboard);
            } elseif ('select_role' === $subAction) {
                $authorId = isset($payload['params'][2]) ? (int) $payload['params'][2] : null;

                $this->userStateRepo->set($userId, ['project_id' => $projectId, 'author_id' => $authorId], 'awaiting_staff_role');

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('staff', 'manage', (string)$projectId)],
                        ],
                    ],
                ];

                $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('enter_staff_role_message'), $keyboard);
            }

            if (isset($update['callback_query']['id'])) {
                $this->client->answerCallbackQuery($update['callback_query']['id']);
            }
        } elseif ('' !== $text) {
            if ($state_data) {
                $message_id = $update['message']['message_id'] ?? null;
                if ($message_id) {
                    $this->client->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message_id]);
                }

                $this->userStateRepo->clear($userId, 'awaiting_staff_role');

                $project_id = $state_data['project_id'];
                $author_id = $state_data['author_id'];
                $role = $text;

                $content_service->assignStaff($project_id, $author_id, $role);
                $author = $this->em->find(Author::class, $author_id);

                $this->client->sendMessage($chatId, str_replace(['{author}', '{role}'], [htmlspecialchars($author->getName()), htmlspecialchars($role)], $this->translate('staff_member_added_message')));
                $this->userStateRepo->clear($userId);

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('staff', 'manage', (string)$project_id)],
                        ],
                    ],
                ];

                $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('staff_member_added_message'), $keyboard);
            }
        }
    }
}
