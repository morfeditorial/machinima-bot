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

namespace morfeditorial\screens\Category;

use morfeditorial\screens\AbstractScreen;

class CategoryManageScreen extends AbstractScreen
{
    private ?int $parentId = null;

    public function setParentId(?int $parentId) : self
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function render() : void
    {
        if (! $this->isGranted('moderator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_state_service = $this->bot->getContainer()->get('user_state_service');
        $user_state_service->clearState($this->userId);

        $content_service = $this->bot->getContainer()->get('content_service');

        $categories = $content_service->getCategoriesByParent($this->parentId);
        $keyboard = ['inline_keyboard' => []];

        foreach ($categories as $category) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $category['name'], 'callback_data' => $this->makePayload('category', 'manage', $category['id'])],
                ['text' => '❌', 'callback_data' => $this->makePayload('category', 'delete', 'confirm', $category['id'])],
            ];
        }

        if ($this->parentId) {
            $add_callback = $this->makePayload('category', 'create', 'sub', $this->parentId);
            $add_text = 'add_subcategory';
            $back_callback = $this->makePayload('category', 'manage');
        } else {
            $add_callback = $this->makePayload('category', 'create', 'main');
            $add_text = 'add_category';
            $back_callback = 'control_panel';
        }

        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate($add_text), 'callback_data' => $add_callback],
        ];
        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate('go_back'), 'callback_data' => $back_callback],
        ];

        $msg = $this->translate('manage_categories');
        if ($this->parentId) {
            $parent_category = $content_service->getCategoryById($this->parentId);
            if ($parent_category) {
                $msg = str_replace('{category}', htmlspecialchars($parent_category['name']), $this->translate('manage_subcategories'));
            }
        }

        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

        $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $msg, $keyboard);
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('manage' === $action) {
            $this->parentId = isset($params[0]) ? (int) $params[0] : null;
            $this->render();
        }
    }

    public function handleMessage(string $text) : void
    {
        // Not used
    }
}
