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

namespace morfeditorial\screens\Category;

use morfeditorial\BaseMachinimaScreen;
use App\Entity\User;
use App\Entity\UserState;

class CategoryCreateScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;

        if (str_starts_with($action, 'category:create')) {
            return true;
        }

        if (isset($update['message']['text'])) {
            $tempUser = $this->em->find(User::class, $userId);
            $tempState = $tempUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tempUser, 'stateKey' => 'awaiting_category_name']) : null;
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

        if (! $this->isGranted('ROLE_MODERATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        if (str_starts_with($action, 'category:create')) {
            $payload = $this->parsePayload($action);
            $subAction = $payload['params'][0] ?? '';
            $parentId = isset($payload['params'][1]) ? (int) $payload['params'][1] : null;

            $tmpUser = $this->em->find(User::class, $userId);
            if (!$tmpUser) {
                $tmpUser = new User();
                $tmpUser->setId($userId);
                $this->em->persist($tmpUser);
            }
            $tmpState = $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'awaiting_category_name']);
            if (!$tmpState) {
                $tmpState = new UserState();
                $tmpState->setUser($tmpUser);
                $tmpState->setStateKey('awaiting_category_name');
                $this->em->persist($tmpState);
            }
            $tmpState->setStateValue(json_encode(['parent_id' => $parentId]));
            $this->em->flush();

            $back_callback = $parentId ? $this->makePayload('category', 'manage', (string)$parentId) : $this->makePayload('category', 'manage');

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $back_callback],
                    ],
                ],
            ];

            $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('enter_category_name_message'), $keyboard);

            if (isset($update['callback_query']['id'])) {
                $this->client->answerCallbackQuery($update['callback_query']['id']);
            }
        } elseif ($text !== '') {
            $tmpUser = $this->em->find(User::class, $userId);
            $tmpState = $tmpUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'awaiting_category_name']) : null;
            $state_data = $tmpState ? json_decode($tmpState->getStateValue(), true) : null;

            if ($state_data) {
                $message_id = $update['message']['message_id'] ?? null;
                if ($message_id) {
                    $this->client->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message_id]);
                }

                $content_service = $this->container->get('content_service');
                $parent_id = $state_data['parent_id'] ?? null;
                $name = $text;

                $content_service->createCategory($name, $parent_id);

                $this->client->sendMessage($chatId, str_replace('{name}', htmlspecialchars($name), $this->translate('category_added_message')));
                $userObj = $this->em->find(User::class, $userId);
                if ($userObj) {
                    $state = $this->em->getRepository(UserState::class)->findOneBy(['user' => $userObj, 'stateKey' => 'awaiting_category_name']);
                    if ($state) {
                        $this->em->remove($state);
                        $this->em->flush();
                    }
                }
                
                $back_callback = $parent_id ? $this->makePayload('category', 'manage', (string)$parent_id) : $this->makePayload('category', 'manage');
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => $back_callback],
                        ],
                    ],
                ];

                $this->renderPanel($chatId, $userId, $this->getVisualsLinks()[1], $this->translate('category_added_message'), $keyboard);
            }
        }
    }
}
