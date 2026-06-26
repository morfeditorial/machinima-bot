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

declare(strict_types=1);

namespace morfeditorial\screens\Staff;

use morfeditorial\screens\AbstractScreen;

class StaffRemoveScreen extends AbstractScreen
{
    private int $projectId;
    private int $authorId;
    private string $role;
    private bool $isConfirm = false;

    public function setProjectId(int $projectId) : self
    {
        $this->projectId = $projectId;
        return $this;
    }

    public function setAuthorId(int $authorId) : self
    {
        $this->authorId = $authorId;
        return $this;
    }

    public function setRole(string $role) : self
    {
        $this->role = $role;
        return $this;
    }

    public function setConfirm(bool $isConfirm) : self
    {
        $this->isConfirm = $isConfirm;
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

        if ($this->isConfirm) {
            $content_service = $this->bot->getContainer()->get('content_service');
            $content_service->removeStaff($this->projectId, $this->authorId, $this->role);

            // Transition back to manage
            $screen = new StaffManageScreen($this->bot, $this->data);
            $screen->setProjectId($this->projectId)->render();
        } else {
            // Confirm screen
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('confirm_yes'), 'callback_data' => $this->makePayload('staff', 'remove', 'execute', $this->projectId, $this->authorId, base64_encode($this->role))],
                    ],
                    [
                        ['text' => $this->translate('confirm_no'), 'callback_data' => $this->makePayload('staff', 'manage', $this->projectId)],
                    ],
                ],
            ];
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('confirm_delete_message'), $keyboard);
        }
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('remove' === $action) {
            $subAction = $params[0] ?? '';
            $this->projectId = isset($params[1]) ? (int) $params[1] : 0;
            $this->authorId = isset($params[2]) ? (int) $params[2] : 0;
            $this->role = isset($params[3]) ? base64_decode($params[3]) : '';

            if ('confirm' === $subAction) {
                $this->isConfirm = false; // Show confirm screen
                $this->render();
            } elseif ('execute' === $subAction) {
                $this->isConfirm = true; // Proceed to execute
                $this->render();
            }
        }
    }

    public function handleMessage(string $text) : void
    {
        // Not used
    }
}
