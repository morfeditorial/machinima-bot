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

namespace Morfeditorial\MachinimaBotBundle\Screens\Project;

use Morfeditorial\MachinimaBotBundle\BaseMachinimaScreen;
use Morfeditorial\MachinimaCoreBundle\Entity\Content;
use Morfeditorial\MachinimaCoreBundle\Security\Voter\PostVoter;

class ProjectViewScreen extends BaseMachinimaScreen
{
    public function supports(array $update) : bool
    {
        $action = $update['callback_query']['data'] ?? '';
        return 0 === strpos($action, 'project:view') || 0 === strpos($action, 'project:transition');
    }

    public function handle(array $update) : void
    {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? $update['message']['chat']['id'] ?? 0;
        $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? 0;
        $action = $update['callback_query']['data'] ?? '';

        if (!$this->isGranted('ROLE_CREATOR')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $content_service = $this->container->get('content_service');
        $visuals_links = $this->getVisualsLinks();

        $parsed = $this->parsePayload($action);
        $subAction = $parsed['action'] ?? '';

        if ('view' === $subAction) {
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

                $contentEntity = $this->em->find(Content::class, $project_id);
                $canManage = $contentEntity && $this->isGranted(PostVoter::EDIT, $contentEntity);
                $keyboard = ['inline_keyboard' => []];

                if ($canManage) {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => $this->translate('edit_project'), 'callback_data' => $this->makePayload('project', 'edit', (string)$project_id)],
                        ['text' => $this->translate('manage_staff'), 'callback_data' => $this->makePayload('staff', 'manage', (string)$project_id)],
                    ];
                    $keyboard['inline_keyboard'][] = [
                        ['text' => $this->translate('select_categories_for_project'), 'callback_data' => 'select_project_categories:' . $project_id],
                        ['text' => $this->translate('delete_this_project'), 'callback_data' => $this->makePayload('project', 'delete', (string)$project_id)],
                    ];
                }

                $keyboard['inline_keyboard'][] = [
                    ['text' => $this->translate('go_back'), 'callback_data' => $this->userRepo->getCurrentPage($userId) ?? $this->makePayload('project', 'list')],
                ];

                $transition_buttons = [];
                if ('draft' === $project['status'] && $this->isGranted('ROLE_CREATOR')) {
                    $transition_buttons[] = ['text' => $this->translate('submit_project'), 'callback_data' => 'project:transition:' . $project_id . ':submit'];
                } elseif ('pending_review' === $project['status'] && $this->isGranted('ROLE_MODERATOR')) {
                    $transition_buttons[] = ['text' => $this->translate('publish_project'), 'callback_data' => 'project:transition:' . $project_id . ':publish'];
                    $transition_buttons[] = ['text' => $this->translate('reject_project'), 'callback_data' => 'project:transition:' . $project_id . ':reject'];
                } elseif ('rejected' === $project['status'] && $this->isGranted('ROLE_MODERATOR')) {
                    $transition_buttons[] = ['text' => $this->translate('redraft_project'), 'callback_data' => 'project:transition:' . $project_id . ':re-draft'];
                }

                if (!empty($transition_buttons)) {
                    array_unshift($keyboard['inline_keyboard'], $transition_buttons);
                }

                $cover = $project['cover_file_id'] ?: $visuals_links[1];

                $this->renderPanel($chatId, $userId, $cover, $message_text, $keyboard);
            }
        } elseif ('transition' === $subAction) {
            $projectId = (int)($parsed['params'][0] ?? 0);
            $transition = $parsed['params'][1] ?? '';

            $success = $content_service->applyTransition($projectId, $transition);

            if ($success) {
                // Re-render the view
                $updateCopy = $update;
                $updateCopy['callback_query']['data'] = $this->makePayload('project', 'view', (string)$projectId);
                $this->handle($updateCopy);

                // Show an alert to user
                $this->client->sendMessage($chatId, $this->translate('status_updated_message'));
            } else {
                $this->client->sendMessage($chatId, $this->translate('error_transition_message'));
            }
        }
    }
}
