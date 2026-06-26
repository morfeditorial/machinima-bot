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

namespace morfeditorial\screens\Staff;

use morfeditorial\screens\AbstractScreen;

class StaffAddScreen extends AbstractScreen
{
    private int $projectId;
    private int $page = 1;
    private ?int $authorId = null;

    public function setProjectId(int $projectId) : self
    {
        $this->projectId = $projectId;
        return $this;
    }

    public function setPage(int $page) : self
    {
        $this->page = $page;
        return $this;
    }

    public function setAuthorId(?int $authorId) : self
    {
        $this->authorId = $authorId;
        return $this;
    }

    public function render() : void
    {
        if (! $this->isGranted('creator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

        if (null !== $this->authorId) {
            // Awaiting staff role
            $user_state_service = $this->bot->getContainer()->get('user_state_service');
            $user_state_service->setState($this->userId, ['project_id' => $this->projectId, 'author_id' => $this->authorId], 'awaiting_staff_role');

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('staff', 'manage', $this->projectId)],
                    ],
                ],
            ];
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('enter_staff_role_message'), $keyboard);
        } else {
            // Select author
            $keyboard = $this->bot->generateAuthorsKeyboard($this->page, 3, 1, "staff:add:select_role:{$this->projectId}:", "staff:add:select_author:{$this->projectId}:page:");
            // Replace the default go_back from generateAuthorsKeyboard
            array_pop($keyboard['inline_keyboard']);
            $keyboard['inline_keyboard'][] = [['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('staff', 'manage', $this->projectId)]];

            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('select_author_for_staff_message'), $keyboard);
        }
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('add' === $action) {
            $subAction = $params[0] ?? '';
            $this->projectId = isset($params[1]) ? (int) $params[1] : 0;

            if ('select_author' === $subAction) {
                if (isset($params[2]) && 'page' === $params[2]) {
                    $this->page = isset($params[3]) ? (int) $params[3] : 1;
                }
                $this->authorId = null;
                $this->render();
            } elseif ('select_role' === $subAction) {
                $this->authorId = isset($params[2]) ? (int) $params[2] : null;
                $this->render();
            }
        }
    }

    public function handleMessage(string $text) : void
    {
        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $state_data = $user_state_service->getState($this->userId, 'awaiting_staff_role');

        if ($state_data) {
            $message_id = $this->data['message']['message_id'] ?? null;
            if ($message_id) {
                $this->bot->deleteMessage($this->chatId, $message_id);
            }

            $user_state_service->clearState($this->userId, 'awaiting_staff_role');
            $content_service = $this->bot->getContainer()->get('content_service');
            $author_service = $this->bot->getContainer()->get('author_service');

            $project_id = $state_data['project_id'];
            $author_id = $state_data['author_id'];
            $role = $text;

            $content_service->assignStaff($project_id, $author_id, $role);
            $author = $author_service->getAuthorById($author_id);

            $this->bot->sendMessage($this->chatId, str_replace(['{author}', '{role}'], [htmlspecialchars($author['name']), htmlspecialchars($role)], $this->translate('staff_member_added_message')));
            $user_state_service->clearState($this->userId);

            // Transition to StaffManageScreen
            $screen = new StaffManageScreen($this->bot, $this->data);
            $screen->setProjectId($project_id)->render();
        }
    }
}
