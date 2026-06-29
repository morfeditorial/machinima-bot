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

class RoleUserManageScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        if (str_starts_with($action, 'role:user')) {
            return true;
        }

        $userId = $update['message']['from']['id'] ?? 0;
        if ($userId) {
            $tempUser = $this->em->find(User::class, $userId);
            $tempState = $tempUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tempUser, 'stateKey' => 'awaiting_user_id_for_management']) : null;
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

        $role_service = $this->getRoleService();
        $visuals_links = $this->getVisualsLinks();

        $parsed = $this->parsePayload($action);
        
        $internalAction = '';
        $targetUserId = 0;
        
        if ($action && str_starts_with($action, 'role:user')) {
            $subAction = $parsed['params'][0] ?? 'ask_user';
            $targetUserId = (int) ($parsed['params'][1] ?? 0);
            $roleName = $parsed['params'][2] ?? '';

            if ('ask_user' === $subAction) {
                $tmpUser = $this->em->find(User::class, $userId);
                if (!$tmpUser) {
                    $tmpUser = new User();
                    $tmpUser->setId($userId);
                    $this->em->persist($tmpUser);
                }
                $tmpState = $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'awaiting_user_id_for_management']);
                if (!$tmpState) {
                    $tmpState = new UserState();
                    $tmpState->setUser($tmpUser);
                    $tmpState->setStateKey('awaiting_user_id_for_management');
                    $this->em->persist($tmpState);
                }
                $tmpState->setStateValue(json_encode(['active' => true]));
                $this->em->flush();
                $internalAction = 'ask_user';
            } elseif ('do_add' === $subAction) {
                $result = $role_service->assignRole($targetUserId, $roleName);
                if ('success' === $result) {
                    $this->client->sendMessage($chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($roleName), (string)$targetUserId], $this->translate('assign_role_message')));
                } else {
                    $this->client->sendMessage($chatId, $this->translate('role_assignment_failure_message'));
                }
                $internalAction = 'detail';
            } elseif ('do_remove' === $subAction) {
                $result = $role_service->removeUserRole($targetUserId, $roleName);
                if ($result) {
                    $this->client->sendMessage($chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($roleName), (string)$targetUserId], $this->translate('remove_role_message')));
                } else {
                    $this->client->sendMessage($chatId, $this->translate('remove_role_failed_message'));
                }
                $internalAction = 'detail';
            } else {
                $internalAction = $subAction;
            }
        } elseif ($text) {
            $tmpUser = $this->em->find(User::class, $userId);
            $tmpState = $tmpUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'awaiting_user_id_for_management']) : null;
            $state_data = $tmpState ? json_decode($tmpState->getStateValue(), true) : null;
            if (false !== $state_data) {
                $targetUserId = (int) $text;

                if ($targetUserId <= 0) {
                    $this->client->sendMessage($chatId, $this->translate('invalid_user_id_message'));
                    return;
                }

                $messageId = $update['message']['message_id'] ?? 0;
                if ($messageId > 0) {
                    $this->client->request('deleteMessage', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);
                }

                $userObj = $this->em->find(User::class, $userId);
                if ($userObj) {
                    $state = $this->em->getRepository(UserState::class)->findOneBy(['user' => $userObj, 'stateKey' => 'awaiting_user_id_for_management']);
                    if ($state) {
                        $this->em->remove($state);
                        $this->em->flush();
                    }
                }
                $internalAction = 'detail';
            }
        }

        if ('ask_user' === $internalAction) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => 'role:control'],
                    ],
                ],
            ];
            
            $caption = $this->translate('enter_user_id_for_management_message');
            $this->renderPanel($chatId, $userId, $visuals_links[1], $caption, $keyboard);
            return;
        }

        if ($targetUserId <= 0) {
            return;
        }

        $userRoles = $role_service->getUserRoleNames($targetUserId);

        if ('detail' === $internalAction) {
            $rolesText = empty($userRoles) ? "—" : "▫️ <b>" . implode("</b>\n▫️ <b>", $userRoles) . "</b>";
            $caption = str_replace(['{userId}', '{roles}'], [$targetUserId, $rolesText], $this->translate('user_roles_detail_message'));

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('add_role'), 'callback_data' => $this->makePayload('role', 'user', 'add', (string)$targetUserId)],
                        ['text' => $this->translate('remove_role'), 'callback_data' => $this->makePayload('role', 'user', 'remove', (string)$targetUserId)],
                    ],
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'user')],
                    ],
                ],
            ];
            
            $this->renderPanel($chatId, $userId, $visuals_links[1], $caption, $keyboard);
            return;
        }

        if ('add' === $internalAction) {
            $allRoles = $role_service->getAllRolesSorted();
            $keyboard = ['inline_keyboard' => []];
            foreach ($allRoles as $role) {
                if (!in_array($role['role_name'], $userRoles, true)) {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => $role['role_name'], 'callback_data' => $this->makePayload('role', 'user', 'do_add', (string)$targetUserId, $role['role_name'])],
                    ];
                }
            }
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'user', 'detail', (string)$targetUserId)],
            ];
            $caption = str_replace('{userId}', (string)$targetUserId, $this->translate('select_role_to_add_message'));
            
            $this->renderPanel($chatId, $userId, $visuals_links[1], $caption, $keyboard);
            return;
        }

        if ('remove' === $internalAction) {
            $keyboard = ['inline_keyboard' => []];
            foreach ($userRoles as $role_name) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => $role_name, 'callback_data' => $this->makePayload('role', 'user', 'do_remove', (string)$targetUserId, $role_name)],
                ];
            }
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('role', 'user', 'detail', (string)$targetUserId)],
            ];
            $caption = str_replace('{userId}', (string)$targetUserId, $this->translate('select_role_to_remove_message'));
            
            $this->renderPanel($chatId, $userId, $visuals_links[1], $caption, $keyboard);
            return;
        }
    }
}
