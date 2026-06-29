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

class ProjectListScreen extends BaseMachinimaScreen
{
    public function supports(array $update): bool
    {
        $action = $update['callback_query']['data'] ?? '';
        return strpos($action, 'project:list') === 0;
    }

    public function handle(array $update): void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';

        $content_service = $this->container->get('content_service');
        $visuals_links = $this->getVisualsLinks();

        $this->userStateRepo->clear($userId);
        $parsed = $this->parsePayload($action);
        $page = isset($parsed['params'][0]) ? (int)$parsed['params'][0] : 1;
        if ($page < 1) {
            $page = 1;
        }

        $this->userRepo->setCurrentPage($userId, $this->makePayload('project', 'list', (string)$page));

        $all_projects = $content_service->getAllContent();
        $projects_per_page = 5;
        $total_pages = ceil(count($all_projects) / $projects_per_page);
        $offset = ($page - 1) * $projects_per_page;
        $projects_slice = array_slice($all_projects, $offset, $projects_per_page);

        $keyboard = ['inline_keyboard' => []];
        foreach ($projects_slice as $project) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $project['title'], 'callback_data' => $this->makePayload('project', 'view', (string)$project['id'])],
            ];
        }

        if ($total_pages > 1) {
            $pagination = [];
            if ($page > 1) {
                $pagination[] = ['text' => $this->translate('previous_page'), 'callback_data' => $this->makePayload('project', 'list', (string)($page - 1))];
            }
            if ($page < $total_pages) {
                $pagination[] = ['text' => $this->translate('next_page'), 'callback_data' => $this->makePayload('project', 'list', (string)($page + 1))];
            }
            $keyboard['inline_keyboard'][] = $pagination;
        }

        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate('add_project'), 'callback_data' => $this->makePayload('project', 'create')],
        ];
        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate('go_back'), 'callback_data' => 'admin:panel'],
        ];

        $this->renderPanel($chatId, $userId, $visuals_links[1], $this->translate('manage_projects'), $keyboard, true);
    }
}
