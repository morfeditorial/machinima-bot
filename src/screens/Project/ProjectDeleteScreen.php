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

class ProjectDeleteScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        return 0 === strpos($action, 'project:delete');
    }

    public function handle(array $update) : void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';
        $callbackQueryId = $update['callback_query']['id'] ?? '';

        if (!$this->isGranted('ROLE_CREATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $content_service = $this->container->get('content_service');
        $visuals_links = $this->getVisualsLinks();

        $parsed = $this->parsePayload($action);
        $project_id = isset($parsed['params'][0]) ? (int)$parsed['params'][0] : 0;
        $subAction = isset($parsed['params'][1]) ? $parsed['params'][1] : 'prompt';

        if (!$content_service->canManageProject($userId, $project_id, $this->isGranted('ROLE_MODERATOR'))) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        if ('prompt' === $subAction) {
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

                $caption = str_replace('{title}', htmlspecialchars($project['title']), $this->translate('confirm_delete_project_message'));

                $this->renderPanel($chatId, $userId, $visuals_links[1], $caption, $keyboard);
            }
        } elseif ('confirm' === $subAction) {
            if ($content_service->deleteContent($project_id)) {
                if ($callbackQueryId) {
                    $this->client->request('answerCallbackQuery', [
                        'callback_query_id' => $callbackQueryId,
                        'text' => $this->translate('project_deleted_message')
                    ]);
                }
            }

            $updateCopy = $update;
            $updateCopy['callback_query']['data'] = $this->makePayload('project', 'list');
            $screen = new ProjectListScreen();
            $screen->setClient($this->client);
            $screen->setDependencies($this->container, $this->security);
            $screen->handle($updateCopy);
        }
    }
}
