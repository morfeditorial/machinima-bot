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

use morfeditorial\BaseMachinimaScreen;
use App\Entity\User;
use App\Entity\UserState;

class RoleUnassignScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        if (str_starts_with($action, 'role:unassign')) {
            return true;
        }

        $userId = $update['message']['from']['id'] ?? 0;
        if ($userId) {
            $tempUser = $this->em->find(User::class, $userId);
            $tempState = $tempUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tempUser, 'stateKey' => 'awaiting_user_id_to_remove_role']) : null;
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

        if (! $this->isGranted('ROLE_ADMIN')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $visuals_links = $this->getVisualsLinks();
        if ($action && str_starts_with($action, 'role:unassign')) {
            $parsed = $this->parsePayload($action);
            $subAction = $parsed['params'][0] ?? '';

            if ('ask_user' === $subAction) {
                $role_name = $parsed['params'][1] ?? '';
                $tmpUser = $this->em->find(User::class, $userId);
                if (!$tmpUser) {
                    $tmpUser = new User();
                    $tmpUser->setId($userId);
                    $this->em->persist($tmpUser);
                }
                $tmpState = $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'awaiting_user_id_to_remove_role']);
                if (!$tmpState) {
                    $tmpState = new UserState();
                    $tmpState->setUser($tmpUser);
                    $tmpState->setStateKey('awaiting_user_id_to_remove_role');
                    $this->em->persist($tmpState);
                }
                $tmpState->setStateValue(json_encode(['role_name' => $role_name]));
                $this->em->flush();

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'view', 'show', $role_name)],
                        ],
                    ],
                ];

                $caption = str_replace('{role}', $role_name, $this->translate('enter_user_id_to_remove_role_message'));

                $this->renderPanel($chatId, $userId, $visuals_links[1], $caption, $keyboard);
            }
            return;
        }

        if ($text) {
            $tmpUser = $this->em->find(User::class, $userId);
            $tmpState = $tmpUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'awaiting_user_id_to_remove_role']) : null;
            $state_data = $tmpState ? json_decode($tmpState->getStateValue(), true) : null;
            if ($state_data) {
                $role_service = $this->getRoleService();
                $target_user_id = (int) $text;

                if ($target_user_id <= 0) {
                    $this->client->sendMessage($chatId, $this->translate('invalid_user_id_message'));
                    return;
                }

                $role_name = $state_data['role_name'] ?? '';
                $result = $role_service->removeUserRole($target_user_id, $role_name);

                if ($result) {
                    $this->client->sendMessage($chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('remove_role_message')));
                } else {
                    $this->client->sendMessage($chatId, $this->translate('remove_role_failed_message'));
                }

                $userObj = $this->em->find(User::class, $userId);
                if ($userObj) {
                    $state = $this->em->getRepository(UserState::class)->findOneBy(['user' => $userObj, 'stateKey' => 'awaiting_user_id_to_remove_role']);
                    if ($state) {
                        $this->em->remove($state);
                        $this->em->flush();
                    }
                }

                // Return to view screen
                $viewScreen = new RoleViewScreen();
                $viewScreen->setDependencies($this->container, $this->security);
                $viewScreen->setClient($this->client);
                $fakeUpdate = $update;
                $fakeUpdate['callback_query'] = [
                    'data' => "role:view:show:{$role_name}",
                    'message' => ['chat' => ['id' => $chatId]],
                    'from' => ['id' => $userId]
                ];
                $viewScreen->handle($fakeUpdate);
            }
        }
    }
}
