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

class ProjectViewScreen extends AbstractScreen
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

        $project = $content_service->getContentById($project_id);

        if ($project) {
            $staff = $content_service->getStaffByContentId($project_id);
            $staff_text = "";
            foreach ($staff as $member) {
                $staff_text .= "\n- " . htmlspecialchars($member['author_name']) . " (" . htmlspecialchars($member['role']) . ")";
            }

            $categories = $content_service->getCategoriesByContentId($project_id);
            $categories_names = array_column($categories, 'name');
            $categories_text = !empty($categories_names) ? implode(', ', $categories_names) : "\u{2014}";

            $message_text = "📦 <b>" . htmlspecialchars($project['title']) . "</b>\n";
            $message_text .= "📝 " . htmlspecialchars($project['description'] ?? '') . "\n";
            $message_text .= "📊 Статус: " . $project['status'] . "\n";
            $message_text .= "🏷 Категорії: " . htmlspecialchars($categories_text) . "\n";
            $message_text .= "\n👥 Команда:" . ($staff_text ?: " \u{2014}");

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('edit_project'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id)],
                        ['text' => $this->translate('manage_staff'), 'callback_data' => 'manage_staff:' . $project_id],
                    ],
                    [
                        ['text' => $this->translate('select_categories_for_project'), 'callback_data' => 'select_project_categories:' . $project_id],
                        ['text' => $this->translate('delete_this_project'), 'callback_data' => $this->makePayload('project', 'delete', (string)$project_id)],
                    ],
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $user_service->getCurrentPage($this->userId) ?? $this->makePayload('project', 'list')],
                    ],
                ],
            ];

            $transition_buttons = [];
            if ('draft' === $project['status'] && $this->isGranted('creator')) {
                $transition_buttons[] = ['text' => $this->translate('submit_project'), 'callback_data' => 'project:transition:' . $project_id . ':submit'];
            } elseif ('pending_review' === $project['status'] && $this->isGranted('moderator')) {
                $transition_buttons[] = ['text' => $this->translate('publish_project'), 'callback_data' => 'project:transition:' . $project_id . ':publish'];
                $transition_buttons[] = ['text' => $this->translate('reject_project'), 'callback_data' => 'project:transition:' . $project_id . ':reject'];
            } elseif ('rejected' === $project['status'] && $this->isGranted('moderator')) {
                $transition_buttons[] = ['text' => $this->translate('redraft_project'), 'callback_data' => 'project:transition:' . $project_id . ':re-draft'];
            }

            if (!empty($transition_buttons)) {
                array_unshift($keyboard['inline_keyboard'], $transition_buttons);
            }

            $current_panel = $user_service->getCurrentPanel($this->userId);
            if ($project['cover_file_id']) {
                $this->bot->editMediaMessage($this->chatId, $current_panel, $project['cover_file_id'], $message_text, $keyboard);
            } else {
                $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $message_text, $keyboard);
            }
        }
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('view' === $action) {
            $this->data['payload'] = $this->makePayload('project', 'view', $params[0] ?? '0');
            $this->render();
        } elseif ('transition' === $action) {
            $projectId = (int)($params[0] ?? 0);
            $transition = $params[1] ?? '';

            $contentService = $this->bot->getContainer()->get('content_service');
            $success = $contentService->applyTransition($projectId, $transition);

            if ($success) {
                // Re-render the view
                $this->data['payload'] = $this->makePayload('project', 'view', (string)$projectId);
                $this->render();

                // Show an alert to user
                $this->bot->sendMessage($this->chatId, $this->translate('status_updated_message'));
            } else {
                $this->bot->sendMessage($this->chatId, $this->translate('error_transition_message'));
            }
        }
    }

    public function handleMessage(string $text) : void
    {
        // Не чекаємо тексту
    }
}
