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

class ProjectListScreen extends AbstractScreen
{
    public function render() : void
    {
        $user_service = $this->bot->getContainer()->get('user_service');
        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $content_service = $this->bot->getContainer()->get('content_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');

        $user_state_service->clearState($this->userId);
        $current_panel = $user_service->getCurrentPanel($this->userId);

        $payload = $this->data['payload'] ?? '';
        $parsed = $this->parsePayload($payload);
        $page = isset($parsed['params'][0]) ? (int)$parsed['params'][0] : 1;
        if ($page < 1) {
            $page = 1;
        }

        $user_service->setCurrentPage($this->userId, $this->makePayload('project', 'list', (string)$page));

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

        $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('manage_projects'), $keyboard);
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('list' === $action) {
            $this->data['payload'] = $this->makePayload('project', 'list', $params[0] ?? '1');
            $this->render();
        }
    }

    public function handleMessage(string $text) : void
    {
        // Не чекаємо тексту
    }
}
