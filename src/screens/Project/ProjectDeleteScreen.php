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

class ProjectDeleteScreen extends AbstractScreen
{
    public function render() : void
    {
        if (!$this->isGranted('creator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_service = $this->bot->getContainer()->get('user_service');
        $content_service = $this->bot->getContainer()->get('content_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');

        $payload = $this->data['payload'] ?? '';
        $parsed = $this->parsePayload($payload);
        $project_id = isset($parsed['params'][0]) ? (int)$parsed['params'][0] : 0;
        $action = isset($parsed['params'][1]) ? $parsed['params'][1] : 'prompt';
        $current_panel = $user_service->getCurrentPanel($this->userId);

        if (!$content_service->canManageProject($this->userId, $project_id, $this->isGranted('moderator'))) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        if ('prompt' === $action) {
            $project = $content_service->getContentById($project_id);
            if ($project) {
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('confirm_yes'), 'callback_data' => $this->makePayload('project', 'delete', (string)$project_id, 'confirm')],
                        ],
                        [
                            ['text' => $this->translate('confirm_no'), 'callback_data' => $this->makePayload('project', 'view', (string)$project_id)],
                        ],
                    ],
                ];
                $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], str_replace('{title}', htmlspecialchars($project['title']), $this->translate('confirm_delete_project_message')), $keyboard);
            }
        } elseif ('confirm' === $action) {
            if ($content_service->deleteContent($project_id)) {
                $this->bot->callbackAnswer($this->data['callback_query_id'] ?? '', $this->translate('project_deleted_message'));
            }
            $this->data['payload'] = $this->makePayload('project', 'list');
            $screen = new ProjectListScreen($this->bot, $this->data);
            $screen->render();
        }
    }
}
