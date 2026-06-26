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

use morfeditorial\screens\AbstractScreen;

class ProjectCreateScreen extends AbstractScreen
{
    public function render() : void
    {
        if (!$this->isGranted('creator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');

        $payload = $this->data['payload'] ?? null;
        $message = $this->data['message'] ?? null;
        $message_id = $this->data['message_id'] ?? null;
        $current_panel = $user_service->getCurrentPanel($this->userId);

        if ($payload === $this->makePayload('project', 'create')) {
            $user_state_service->setState($this->userId, 'awaiting_project_title');
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'list')],
                    ],
                ],
            ];
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('enter_project_title_message'), $keyboard);
            return;
        }

        $default_state = $user_state_service->getState($this->userId);

        if ('awaiting_project_title' === $default_state) {
            $this->bot->deleteMessage($this->chatId, $message_id);
            $user_state_service->clearState($this->userId, 'default');
            $user_state_service->setState($this->userId, ['title' => $message], 'awaiting_project_description');

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
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('select_project_type_message'), $keyboard);
        } elseif ($state_data = $user_state_service->getState($this->userId, 'awaiting_project_description')) {
            $this->bot->deleteMessage($this->chatId, $message_id);
            $user_state_service->clearState($this->userId, 'awaiting_project_description');
            $user_state_service->setState($this->userId, array_merge($state_data, ['description' => $message]), 'awaiting_project_url');

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'list')],
                    ],
                ],
            ];
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('enter_project_url_message'), $keyboard);
        } elseif ($state_data = $user_state_service->getState($this->userId, 'awaiting_project_url')) {
            $this->bot->deleteMessage($this->chatId, $message_id);
            $user_state_service->clearState($this->userId, 'awaiting_project_url');
            $user_state_service->setState($this->userId, array_merge($state_data, ['url' => $message]), 'awaiting_project_cover');

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('project', 'list')],
                    ],
                ],
            ];
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('upload_project_cover_message'), $keyboard);
        } elseif ($state_data = $user_state_service->getState($this->userId, 'awaiting_project_cover')) {
            $photo = $this->data['photo'] ?? null;
            if ($photo) {
                $file_id = end($photo)['file_id'];
                $this->bot->deleteMessage($this->chatId, $message_id);

                $content_service = $this->bot->getContainer()->get('content_service');
                $content_service->createContent([
                    'title' => $state_data['title'],
                    'type' => $state_data['type'],
                    'description' => $state_data['description'],
                    'url' => $state_data['url'],
                    'status' => 'draft',
                    'cover_file_id' => $file_id,
                    'created_by' => $this->userId,
                ]);

                $user_state_service->clearState($this->userId);
                $this->bot->sendMessage($this->chatId, str_replace('{title}', htmlspecialchars($state_data['title']), $this->translate('project_created_message')));

                $this->data['payload'] = $this->makePayload('project', 'list');
                $screen = new ProjectListScreen($this->bot, $this->data);
                $screen->render();
            }
        }
    }
}
