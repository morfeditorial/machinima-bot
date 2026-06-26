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

class CategoryDeleteScreen extends AbstractScreen
{
    private int $categoryId;
    private bool $isConfirm = false;

    public function setCategoryId(int $categoryId) : self
    {
        $this->categoryId = $categoryId;
        return $this;
    }

    public function setConfirm(bool $isConfirm) : self
    {
        $this->isConfirm = $isConfirm;
        return $this;
    }

    public function render() : void
    {
        if (! $this->isGranted('moderator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        $user_service = $this->bot->getContainer()->get('user_service');
        $visuals_links = $this->bot->getContainer()->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($this->userId);

        if ($this->isConfirm) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('confirm_yes'), 'callback_data' => $this->makePayload('category', 'delete', 'execute', $this->categoryId)],
                    ],
                    [
                        ['text' => $this->translate('confirm_no'), 'callback_data' => $this->makePayload('category', 'manage')],
                    ],
                ],
            ];
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], str_replace('{name}', 'ID ' . $this->categoryId, $this->translate('confirm_delete_category_message')), $keyboard);
        } else {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $this->makePayload('category', 'manage')],
                    ],
                ],
            ];
            $this->bot->editMediaMessage($this->chatId, $current_panel, $visuals_links[1], $this->translate('category_deleted_message'), $keyboard);
        }
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('delete' === $action) {
            $subAction = $params[0] ?? '';
            $categoryId = isset($params[1]) ? (int) $params[1] : 0;

            $this->categoryId = $categoryId;

            if ('confirm' === $subAction) {
                $this->isConfirm = true;
                $this->render();
            } elseif ('execute' === $subAction) {
                // Execute deletion here in the controller
                if ($this->isGranted('moderator')) {
                    $this->bot->getContainer()->get('content_service')->db->executeStatement('DELETE FROM categories WHERE id = ?', [$this->categoryId]);
                }

                $this->isConfirm = false;
                $this->render();
            }
        }
    }

    public function handleMessage(string $text) : void
    {
        // Not used
    }
}
