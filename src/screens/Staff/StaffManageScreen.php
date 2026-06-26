<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \/       \//       \//       \
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
namespace morfeditorial\screens\Staff;

use morfeditorial\screens\AbstractScreen;

class StaffManageScreen extends AbstractScreen
{
    private int $projectId;

    public function setProjectId(int $projectId) : self
    {
        $this->projectId = $projectId;
        return $this;
    }

    public function render() : void
    {
        if (! $this->isGranted('creator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $content_service = $this->bot->getContainer()->get('content_service');
        $staff = $content_service->getStaffByContentId($this->projectId);

        $keyboard = ['inline_keyboard' => []];
        foreach ($staff as $member) {
            $keyboard['inline_keyboard'][] = [
                ['text' => "❌ " . $member['author_name'] . " (" . $member['role'] . ")", 'callback_data' => $this->makePayload('staff', 'remove', 'confirm', $this->projectId, $member['author_id'], base64_encode($member['role']))],
            ];
        }

        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate('add_staff_member'), 'callback_data' => $this->makePayload('staff', 'add', 'select_author', $this->projectId)],
        ];
        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate('go_back'), 'callback_data' => 'view_project:' . $this->projectId],
        ];

        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

        $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('manage_staff'), $keyboard);
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('manage' === $action) {
            $this->projectId = isset($params[0]) ? (int) $params[0] : 0;
            $this->render();
        }
    }

    public function handleMessage(string $text) : void
    {
        // Not used
    }
}
