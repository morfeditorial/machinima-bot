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

use morfeditorial\AbstractCommand;
use morfeditorial\MyBot;

class AssignInitialAdminCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['assign_initial_admin']);
        $this->setHiddenFromMenu(true);
    }

    public function getDescriptionKey() : string
    {
        return 'assign_initial_admin_command_description';
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

        if (! $db_manager->getRoleByName('admin')) {
            $db_manager->createRole('admin', 100);
        }

        if ($db_manager->getUsersCountByRole('admin') > 0) {
            $this->bot->sendMessage($chat_id, $this->translate('already_initialled_admin_message'));

            return;
        }

        $db_manager->assignRole($user_id, 'admin');

        $this->bot->sendMessage($chat_id, $this->translate('success_initialled_admin_message'));
    }
}
