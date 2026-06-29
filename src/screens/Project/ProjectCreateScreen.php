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
use App\Entity\User;
use App\Entity\UserState;

class ProjectCreateScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        if (strpos($action, 'project:create') === 0) {
            return true;
        }

        $userId = $update['message']['from']['id'] ?? 0;
        if ($userId) {
            $tempUser = $this->em->find(User::class, $userId);
            $tempState = $tempUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tempUser, 'stateKey' => 'default']) : null;
            $state = $tempState ? json_decode($tempState->getStateValue(), true) : null;
            if (in_array($state, ['awaiting_project_title'], true)) {
                return true;
            }
            $descState = $tempUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tempUser, 'stateKey' => 'awaiting_project_description']) : null;
            if ($descState) return true;
            $urlState = $tempUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tempUser, 'stateKey' => 'awaiting_project_url']) : null;
            if ($urlState) return true;
            $coverState = $tempUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tempUser, 'stateKey' => 'awaiting_project_cover']) : null;
            if ($coverState) return true;
        }

        return false;
    }

    public function handle(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';
        $text = $update['message']['text'] ?? '';
        $message_id = $update['message']['message_id'] ?? null;
        $photo = $update['message']['photo'] ?? null;

        if (!$this->isGranted('ROLE_CREATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $visuals_links = $this->getVisualsLinks();

        if (strpos($action, 'project:create') === 0) {
            $tmpUser = $this->em->find(User::class, $userId);
            if (!$tmpUser) {
                $tmpUser = new User();
                $tmpUser->setId($userId);
                $this->em->persist($tmpUser);
            }
            $tmpState = $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'default']);
            if (!$tmpState) {
                $tmpState = new UserState();
                $tmpState->setUser($tmpUser);
                $tmpState->setStateKey('default');
                $this->em->persist($tmpState);
            }
            $tmpState->setStateValue(json_encode('awaiting_project_title'));
            $this->em->flush();
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'list')],
                    ],
                ],
            ];
            $this->renderPanel($chatId, $userId, $visuals_links[1], $this->translate('enter_project_title_message'), $keyboard);
            return;
        }

        $tmpUser = $this->em->find(User::class, $userId);
        $tmpState = $tmpUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'default']) : null;
        $default_state = $tmpState ? json_decode($tmpState->getStateValue(), true) : null;

        if ('awaiting_project_title' === $default_state) {
            if ($message_id) {
                $this->client->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message_id]);
            }
            if ($tmpUser && $tmpState) {
                $this->em->remove($tmpState);
                $this->em->flush();
            }
            $tmpUser = $this->em->find(User::class, $userId);
            if (!$tmpUser) {
                $tmpUser = new User();
                $tmpUser->setId($userId);
                $this->em->persist($tmpUser);
            }
            $tmpState = $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'awaiting_project_description']);
            if (!$tmpState) {
                $tmpState = new UserState();
                $tmpState->setUser($tmpUser);
                $tmpState->setStateKey('awaiting_project_description');
                $this->em->persist($tmpState);
            }
            $tmpState->setStateValue(json_encode(['title' => $text]));
            $this->em->flush();

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('project_type_short'), 'callback_data' => $this->makePayload('project', 'set_type', 'short')],
                        ['text' => $this->translate('project_type_series'), 'callback_data' => $this->makePayload('project', 'set_type', 'series')],
                    ],
                    [
                        ['text' => $this->translate('project_type_music_video'), 'callback_data' => $this->makePayload('project', 'set_type', 'music_video')],
                    ],
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'list')],
                    ],
                ],
            ];
            $this->renderPanel($chatId, $userId, $visuals_links[1], $this->translate('select_project_type_message'), $keyboard);
        } elseif ($state_data = ($this->em->find(User::class, $userId) ? ($this->em->getRepository(UserState::class)->findOneBy(['user' => $this->em->find(User::class, $userId), 'stateKey' => 'awaiting_project_description']) ? json_decode($this->em->getRepository(UserState::class)->findOneBy(['user' => $this->em->find(User::class, $userId), 'stateKey' => 'awaiting_project_description'])->getStateValue(), true) : null) : null)) {
            if ($message_id) {
                $this->client->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message_id]);
            }
            $userObj = $this->em->find(User::class, $userId);
            if ($userObj) {
                $state = $this->em->getRepository(UserState::class)->findOneBy(['user' => $userObj, 'stateKey' => 'awaiting_project_description']);
                if ($state) {
                    $this->em->remove($state);
                    $this->em->flush();
                }
            }
            $tmpUser = $this->em->find(User::class, $userId);
            if (!$tmpUser) {
                $tmpUser = new User();
                $tmpUser->setId($userId);
                $this->em->persist($tmpUser);
            }
            $tmpState = $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'awaiting_project_url']);
            if (!$tmpState) {
                $tmpState = new UserState();
                $tmpState->setUser($tmpUser);
                $tmpState->setStateKey('awaiting_project_url');
                $this->em->persist($tmpState);
            }
            $tmpState->setStateValue(json_encode(array_merge($state_data, ['description' => $text])));
            $this->em->flush();

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'list')],
                    ],
                ],
            ];
            $this->renderPanel($chatId, $userId, $visuals_links[1], $this->translate('enter_project_url_message'), $keyboard);
        } elseif ($state_data = ($this->em->find(User::class, $userId) ? ($this->em->getRepository(UserState::class)->findOneBy(['user' => $this->em->find(User::class, $userId), 'stateKey' => 'awaiting_project_url']) ? json_decode($this->em->getRepository(UserState::class)->findOneBy(['user' => $this->em->find(User::class, $userId), 'stateKey' => 'awaiting_project_url'])->getStateValue(), true) : null) : null)) {
            if ($message_id) {
                $this->client->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message_id]);
            }
            $userObj = $this->em->find(User::class, $userId);
            if ($userObj) {
                $state = $this->em->getRepository(UserState::class)->findOneBy(['user' => $userObj, 'stateKey' => 'awaiting_project_url']);
                if ($state) {
                    $this->em->remove($state);
                    $this->em->flush();
                }
            }
            $tmpUser = $this->em->find(User::class, $userId);
            if (!$tmpUser) {
                $tmpUser = new User();
                $tmpUser->setId($userId);
                $this->em->persist($tmpUser);
            }
            $tmpState = $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'awaiting_project_cover']);
            if (!$tmpState) {
                $tmpState = new UserState();
                $tmpState->setUser($tmpUser);
                $tmpState->setStateKey('awaiting_project_cover');
                $this->em->persist($tmpState);
            }
            $tmpState->setStateValue(json_encode(array_merge($state_data, ['url' => $text])));
            $this->em->flush();

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'list')],
                    ],
                ],
            ];
            $this->renderPanel($chatId, $userId, $visuals_links[1], $this->translate('upload_project_cover_message'), $keyboard);
        } elseif ($state_data = ($this->em->find(User::class, $userId) ? ($this->em->getRepository(UserState::class)->findOneBy(['user' => $this->em->find(User::class, $userId), 'stateKey' => 'awaiting_project_cover']) ? json_decode($this->em->getRepository(UserState::class)->findOneBy(['user' => $this->em->find(User::class, $userId), 'stateKey' => 'awaiting_project_cover'])->getStateValue(), true) : null) : null)) {
            if ($photo) {
                $file_id = end($photo)['file_id'];
                if ($message_id) {
                    $this->client->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message_id]);
                }

                $content_service = $this->container->get('content_service');
                $content_service->createContent([
                    'title' => $state_data['title'],
                    'type' => $state_data['type'] ?? 'short',
                    'description' => $state_data['description'],
                    'url' => $state_data['url'],
                    'status' => 'draft',
                    'cover_file_id' => $file_id,
                    'created_by' => $userId,
                ]);

                $userObj = $this->em->find(User::class, $userId);
                if ($userObj) {
                    $states = $this->em->getRepository(UserState::class)->findBy(['user' => $userObj]);
                    foreach ($states as $state) {
                        $this->em->remove($state);
                    }
                    $this->em->flush();
                }
                $this->client->sendMessage($chatId, str_replace('{title}', htmlspecialchars($state_data['title']), $this->translate('project_created_message')));

                $updateCopy = $update;
                $updateCopy['callback_query'] = [
                    'data' => $this->makePayload('project', 'list'),
                    'message' => ['chat' => ['id' => $chatId]],
                    'from' => ['id' => $userId]
                ];
                $screen = new ProjectListScreen();
                $screen->setClient($this->client);
                $screen->setDependencies($this->container, $this->security);
                $screen->handle($updateCopy);
            }
        }
    }
}
