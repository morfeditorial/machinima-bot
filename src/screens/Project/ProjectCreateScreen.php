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
            $state = $this->getUserStateService()->getState($userId);
            if (in_array($state, ['awaiting_project_title'], true)) {
                return true;
            }
            if ($this->getUserStateService()->getState($userId, 'awaiting_project_description') ||
                $this->getUserStateService()->getState($userId, 'awaiting_project_url') ||
                $this->getUserStateService()->getState($userId, 'awaiting_project_cover')) {
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

        if (!$this->isGranted('ROLE_CREATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_state_service = $this->getUserStateService();
        $visuals_links = $this->getVisualsLinks();

        if (strpos($action, 'project:create') === 0) {
            $user_state_service->setState($userId, 'awaiting_project_title');
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

        $default_state = $user_state_service->getState($userId);

        if ('awaiting_project_title' === $default_state) {
            if ($message_id) {
                $this->client->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message_id]);
            }
            $user_state_service->clearState($userId, 'default');
            $user_state_service->setState($userId, ['title' => $text], 'awaiting_project_description');

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
        } elseif ($state_data = $user_state_service->getState($userId, 'awaiting_project_description')) {
            if ($message_id) {
                $this->client->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message_id]);
            }
            $user_state_service->clearState($userId, 'awaiting_project_description');
            $user_state_service->setState($userId, array_merge($state_data, ['description' => $text]), 'awaiting_project_url');

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'list')],
                    ],
                ],
            ];
            $this->renderPanel($chatId, $userId, $visuals_links[1], $this->translate('enter_project_url_message'), $keyboard);
        } elseif ($state_data = $user_state_service->getState($userId, 'awaiting_project_url')) {
            if ($message_id) {
                $this->client->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message_id]);
            }
            $user_state_service->clearState($userId, 'awaiting_project_url');
            $user_state_service->setState($userId, array_merge($state_data, ['url' => $text]), 'awaiting_project_cover');

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'list')],
                    ],
                ],
            ];
            $this->renderPanel($chatId, $userId, $visuals_links[1], $this->translate('upload_project_cover_message'), $keyboard);
        } elseif ($state_data = $user_state_service->getState($userId, 'awaiting_project_cover')) {
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

                $user_state_service->clearState($userId);
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
