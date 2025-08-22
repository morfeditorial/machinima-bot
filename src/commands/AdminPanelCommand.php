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
 * Copyright (c) 2024 Sergiy Chernega
 *
 * @author CSSM Group
 * @link https://cssm.pp.ua/
 *
 *
 */

declare(strict_types=1);

namespace morfeditorial\commands;

use morfeditorial\MyBot;
use morfeditorial\AbstractCommand;

class AdminPanelCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['admin_panel']);
        $this->setHiddenFromMenu(true);
    }

    public function getDescriptionKey() : string
    {
        return 'admin_panel_command_description';
    }

    public function execute(
        string $message,
        int $message_id,
        string $chat_type,
        int $chat_id,
        int $user_id,
        $payload,
        ?int $reply_message_id,
        ?int $reply_author,
        string $first_name,
        $current_panel,
        $current_page,
        string $cmd,
        array $args
    ) : void {
        $db_manager = $this->getDbManager();

        if (! $db_manager->hasHigherRole($user_id, 'moderator')) {
            $this->bot->sendMessage($chat_id, $this->translate('no_permission_message'));

            return;
        }

        $db_manager->clearState($user_id);
        if (! is_null($current_panel)) {
            $this->bot->deleteMessage($chat_id, $current_panel);
        }

        $db_manager->setCurrentPanel($user_id, $message_id + 1);

        if (! is_null($current_page)) {
            $db_manager->resetCurrentPage($user_id);
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('add_author'), 'callback_data' => 'add_author'],
                    ['text' => $this->translate('delete_author'), 'callback_data' => 'delete_author'],
                ],
                [
                    ['text' => $this->translate('list_of_authors'), 'callback_data' => 'list_of_authors'],
                ],
                [
                    ['text' => $this->translate('access_control'), 'callback_data' => 'access_control'],
                ],
            ],
        ];

        $this->bot->pictureReply($chat_id, $this->translate('admin_panel_message'), $this->getVisualsLinks()[1], $keyboard);
    }
}
