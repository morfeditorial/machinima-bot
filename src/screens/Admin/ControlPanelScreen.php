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

namespace morfeditorial\screens\Admin;

use morfeditorial\screens\AbstractScreen;

class ControlPanelScreen extends AbstractScreen
{
    public function render() : void
    {
        if (!$this->isGranted('creator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $this->bot->getUserStateService()->clearState($this->userId);

        $currentPage = $this->bot->getUserService()->getCurrentPage($this->userId);
        if (!is_null($currentPage)) {
            $this->bot->getUserService()->resetCurrentPage($this->userId);
        }

        $keyboard = ['inline_keyboard' => []];

        if ($this->isGranted('moderator')) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('add_author'), 'callback_data' => 'author:add'],
                ['text' => $this->translate('delete_author'), 'callback_data' => 'author:delete:page:1'],
            ];
        }

        $projectRow = [];
        $projectRow[] = ['text' => $this->translate('manage_projects'), 'callback_data' => 'project:manage'];
        if ($this->isGranted('moderator')) {
            $projectRow[] = ['text' => $this->translate('manage_categories'), 'callback_data' => 'category:manage'];
        }
        $keyboard['inline_keyboard'][] = $projectRow;

        if ($this->isGranted('moderator')) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('list_of_authors'), 'callback_data' => 'author:list:1'],
            ];
        }

        if ($this->isGranted('admin')) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('access_control'), 'callback_data' => 'access:control'],
            ];
        }

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[1],
            $this->translate('admin_panel_message'),
            $keyboard
        );
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('panel' === $action) {
            $this->render();
        }
    }

    public function handleMessage(string $text) : void
    {
        // Панель не чекає на текст
        $this->bot->deleteMessage($this->chatId, $this->bot->getLastMessageId());
    }
}
