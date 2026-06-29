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

class ProjectEditScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        if (strpos($action, 'project:edit') === 0) {
            return true;
        }

        $userId = $update['message']['from']['id'] ?? 0;
        if ($userId) {
            $tempUser = $this->em->find(User::class, $userId);
            $tempState = $tempUser ? $this->em->getRepository(UserState::class)->findOneBy(['user' => $tempUser, 'stateKey' => 'editing_project_field']) : null;
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
        $message_id = $update['message']['message_id'] ?? null;
        $photo = $update['message']['photo'] ?? null;
        $callbackQueryId = $update['callback_query']['id'] ?? '';

        if (!$this->isGranted('ROLE_CREATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $content_service = $this->container->get('content_service');
        $visuals_links = $this->getVisualsLinks();

        if (strpos($action, 'project:edit') === 0) {
            $parsed = $this->parsePayload($action);
            $project_id = isset($parsed['params'][0]) ? (int)$parsed['params'][0] : 0;
            $sub_action = isset($parsed['params'][1]) ? $parsed['params'][1] : null;

            if (null === $sub_action) {
                $userObj = $this->em->find(User::class, $userId);
                if ($userObj) {
                    $states = $this->em->getRepository(UserState::class)->findBy(['user' => $userObj]);
                    foreach ($states as $state) {
                        $this->em->remove($state);
                    }
                    $this->em->flush();
                }
                $project = $content_service->getContentById($project_id);
                if ($project) {
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $this->translate('edit_title'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'field', 'title')],
                                ['text' => $this->translate('edit_type'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'field', 'type')],
                            ],
                            [
                                ['text' => $this->translate('edit_description'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'field', 'description')],
                                ['text' => $this->translate('edit_url'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'field', 'url')],
                            ],
                            [
                                ['text' => $this->translate('edit_cover'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'field', 'cover')],
                            ],
                            [
                                ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'view', (string)$project_id)],
                            ],
                        ],
                    ];
                    $cover = $project['cover_file_id'] ?: $visuals_links[1];
                    $this->renderPanel($chatId, $userId, $cover, $this->translate('edit_project'), $keyboard);
                }
            } elseif ('field' === $sub_action) {
                $field = $parsed['params'][2] ?? '';
                if ('type' === $field) {
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $this->translate('project_type_short'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'set_type', 'short')],
                                ['text' => $this->translate('project_type_series'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'set_type', 'series')],
                            ],
                            [
                                ['text' => $this->translate('project_type_music_video'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id, 'set_type', 'music_video')],
                            ],
                            [
                                ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id)],
                            ],
                        ],
                    ];
                    $this->renderPanel($chatId, $userId, $visuals_links[1], $this->translate('select_project_type_message'), $keyboard);
                } else {
                    $tmpUser = $this->em->find(User::class, $userId);
                    if (!$tmpUser) {
                        $tmpUser = new User();
                        $tmpUser->setId($userId);
                        $this->em->persist($tmpUser);
                    }
                    $tmpState = $this->em->getRepository(UserState::class)->findOneBy(['user' => $tmpUser, 'stateKey' => 'editing_project_field']);
                    if (!$tmpState) {
                        $tmpState = new UserState();
                        $tmpState->setUser($tmpUser);
                        $tmpState->setStateKey('editing_project_field');
                        $this->em->persist($tmpState);
                    }
                    $tmpState->setStateValue(json_encode(['project_id' => $project_id, 'field' => $field]));
                    $this->em->flush();
                    $msg_key = 'cover' === $field ? 'upload_project_cover_message' : 'enter_new_value_message';
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id)],
                            ],
                        ],
                    ];
                    $this->renderPanel($chatId, $userId, $visuals_links[1], $this->translate($msg_key), $keyboard);
                }
            } elseif ('set_type' === $sub_action) {
                $type = $parsed['params'][2] ?? '';
                $content_service->updateContent($project_id, ['type' => $type]);
                if ($callbackQueryId) {
                    $this->client->request('answerCallbackQuery', [
                        'callback_query_id' => $callbackQueryId,
                        'text' => $this->translate('project_updated_message')
                    ]);
                }

                $updateCopy = $update;
                $updateCopy['callback_query']['data'] = $this->makePayload('project', 'edit', (string)$project_id);
                $this->handle($updateCopy);
            }
        } elseif ($state_data = ($this->em->find(User::class, $userId) ? ($this->em->getRepository(UserState::class)->findOneBy(['user' => $this->em->find(User::class, $userId), 'stateKey' => 'editing_project_field']) ? json_decode($this->em->getRepository(UserState::class)->findOneBy(['user' => $this->em->find(User::class, $userId), 'stateKey' => 'editing_project_field'])->getStateValue(), true) : null) : null)) {
            if ($message_id) {
                $this->client->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message_id]);
            }
            $userObj = $this->em->find(User::class, $userId);
            if ($userObj) {
                $state = $this->em->getRepository(UserState::class)->findOneBy(['user' => $userObj, 'stateKey' => 'editing_project_field']);
                if ($state) {
                    $this->em->remove($state);
                    $this->em->flush();
                }
            }

            $project_id = $state_data['project_id'];
            $field = $state_data['field'];
            $value = $text;

            if ('cover' === $field) {
                if ($photo) {
                    $value = end($photo)['file_id'];
                } else {
                    return; // Ignore if no photo is sent
                }
            }

            $content_service->updateContent($project_id, [$field => $value]);
            $this->client->sendMessage($chatId, $this->translate('project_updated_message'));

            $updateCopy = $update;
            $updateCopy['callback_query'] = [
                'data' => $this->makePayload('project', 'edit', (string)$project_id),
                'message' => ['chat' => ['id' => $chatId]],
                'from' => ['id' => $userId]
            ];
            $this->handle($updateCopy);
        }
    }
}
