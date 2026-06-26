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
        $project = $this->data['project'] ?? null;
        $project_id = $this->data['project_id'] ?? 0;

        if (!$project) {
            return;
        }

        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

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

    public function handleCallback(string $action, array $params) : void
    {
        if (!$this->isGranted('creator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $content_service = $this->bot->getContainer()->get('content_service');
        $project_id = isset($params[0]) ? (int)$params[0] : 0;
        $subAction = isset($params[1]) ? $params[1] : 'prompt';

        if (!$content_service->canManageProject($this->userId, $project_id, $this->isGranted('moderator'))) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        if ('prompt' === $subAction) {
            $project = $content_service->getContentById($project_id);
            if ($project) {
                $this->data['project'] = $project;
                $this->data['project_id'] = $project_id;
                $this->render();
            }
        } elseif ('confirm' === $subAction) {
            if ($content_service->deleteContent($project_id)) {
                $this->bot->callbackAnswer($this->data['callback_query_id'] ?? '', $this->translate('project_deleted_message'));
            }
            $this->data['payload'] = $this->makePayload('project', 'list');
            $screen = new ProjectListScreen($this->bot, $this->data);
            $screen->render();
        }
    }
}
