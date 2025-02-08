<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \\/       \\//       \//       \
 *  /        //        ///        //      __/
 * /         /         /        _/        _/
 * \__/__/__/\________/\____/___/\_______/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
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
    private $db_manager;

    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setDescription($this->translator->translate($this->getDescriptionKey()));
        $this->setAliases(['admin_panel']);
        $this->setHiddenFromMenu(true);

        $this->db_manager = $this->container->get('db_manager');
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
        if (! $this->db_manager->hasHigherRole($user_id, 'moderator')) {
            $this->bot->sendMessage($chat_id, $this->translator->translate('no_permission_message'));

            return;
        }

        $this->db_manager->clearState($user_id);
        if (! is_null($current_panel)) {
            $this->bot->deleteMessage($chat_id, $current_panel);
        }

        $this->db_manager->setCurrentPanel($user_id, $message_id + 1);

        if (! is_null($current_page)) {
            $this->db_manager->resetCurrentPage($user_id);
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translator->translate('add_author'), 'callback_data' => 'add_author'],
                    ['text' => $this->translator->translate('delete_author'), 'callback_data' => 'delete_author'],
                ],
                [
                    ['text' => $this->translator->translate('list_of_authors'), 'callback_data' => 'list_of_authors'],
                ],
                [
                    ['text' => $this->translator->translate('access_control'), 'callback_data' => 'access_control'],
                ],
            ],
        ];

        $this->bot->pictureReply($chat_id, $this->translator->translate('admin_panel_message'), $this->visuals_links[1], $keyboard);
    }
}
