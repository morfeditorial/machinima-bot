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
        if (! $this->getRoleService()->getRoleByName('ROLE_ADMIN')) {
            $this->getRoleService()->createRole('ROLE_ADMIN');
        }

        if (! $this->getRoleService()->getRoleByName('ROLE_MODERATOR')) {
            $this->getRoleService()->createRole('ROLE_MODERATOR');
        }

        if (! $this->getRoleService()->getRoleByName('ROLE_USER')) {
            $this->getRoleService()->createRole('ROLE_USER');
        }

        $this->getRoleService()->addParentChild('ROLE_ADMIN', 'ROLE_MODERATOR');
        $this->getRoleService()->addParentChild('ROLE_MODERATOR', 'ROLE_USER');

        if ($this->getRoleService()->getUsersCountByRole('ROLE_ADMIN') > 0) {
            $this->bot->sendMessage($chat_id, $this->translate('already_initialled_admin_message'));

            return;
        }

        $this->getRoleService()->assignRole($user_id, 'ROLE_ADMIN');

        $this->bot->sendMessage($chat_id, $this->translate('success_initialled_admin_message'));
    }
}
