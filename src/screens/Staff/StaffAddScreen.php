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
use App\Entity\Author;
use App\Entity\User;
use App\Entity\UserState;

class StaffAddScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;

        if (str_starts_with($action, 'staff:add')) {
            return true;
        }

        if (isset($update['message']['text'])) {
            $tempUser = $this->em->find(User::class, $userId);
            $tempState = $tempUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tempUser, 'stateKey' => 'awaiting_staff_role']) : null;
            if ($tempState) {
                return true;
            }
        }

        return false;
    }

    public function handle(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';
        $text = $update['message']['text'] ?? '';

        if (! $this->isGranted('ROLE_CREATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        if (str_starts_with($action, 'staff:add')) {
            $payload = $this->parsePayload($action);
            $subAction = $payload['params'][0] ?? '';
            $projectId = isset($payload['params'][1]) ? (int) $payload['params'][1] : 0;

            if ('select_author' === $subAction) {
                $page = 1;
                if (isset($payload['params'][2]) && 'page' === $payload['params'][2]) {
                    $page = isset($payload['params'][3]) ? (int) $payload['params'][3] : 1;
                }

                $allAuthors = $this->em->getRepository(Author::class)->findAll();
                $keyboard = \morfeditorial\utils\KeyboardHelper::generateAuthorsKeyboard(
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

                $tmpUser = $this->em->find(User::class, $userId);
                if (!$tmpUser) {
                    $tmpUser = new User();
                    $tmpUser->setId($userId);
                    $this->em->persist($tmpUser);
                }
                $tmpState = $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'awaiting_staff_role']);
                if (!$tmpState) {
                    $tmpState = new UserState();
                    $tmpState->setUser($tmpUser);
                    $tmpState->setStateKey('awaiting_staff_role');
                    $this->em->persist($tmpState);
                }
                $tmpState->setStateValue(json_encode(['project_id' => $projectId, 'author_id' => $authorId]));
                $this->em->flush();

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
        } elseif ($text !== '') {
            $tmpUser = $this->em->find(User::class, $userId);
            $tmpState = $tmpUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'awaiting_staff_role']) : null;
            $state_data = $tmpState ? json_decode($tmpState->getStateValue(), true) : null;

            if ($state_data) {
                $message_id = $update['message']['message_id'] ?? null;
                if ($message_id) {
                    $this->client->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message_id]);
                }

                if ($tmpUser && $tmpState) {
                    $this->em->remove($tmpState);
                    $this->em->flush();
                }
                $content_service = $this->container->get('content_service');

                $project_id = $state_data['project_id'];
                $author_id = $state_data['author_id'];
                $role = $text;

                $content_service->assignStaff($project_id, $author_id, $role);
                $author = $this->em->find(Author::class, $author_id);

                $this->client->sendMessage($chatId, str_replace(['{author}', '{role}'], [htmlspecialchars($author->getName()), htmlspecialchars($role)], $this->translate('staff_member_added_message')));
                $userObj = $this->em->find(User::class, $userId);
                if ($userObj) {
                    $states = $this->em->getRepository(UserState::class)->findBy(['user' => $userObj]);
                    foreach ($states as $state) {
                        $this->em->remove($state);
                    }
                    $this->em->flush();
                }

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
