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

class CreateRoleCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['create_role']);
        $this->setHiddenFromMenu(true);
    }

    public function getDescriptionKey() : string
    {
        return 'create_role_command_description';
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

        if (! $db_manager->hasHigherRole($user_id, 'admin')) {
            $this->bot->sendMessage($chat_id, $this->translate('no_permission_message'));

            return;
        }

        if (2 > count($args)) {
            $this->bot->sendMessage($chat_id, $this->translate('create_role_usage_message'));

            return;
        }

        $role_name = $args[0];
        $priority = intval($args[1]);

        if ($db_manager->getRoleByName($role_name)) {
            $this->bot->sendMessage($chat_id, str_replace('{roleName}', htmlspecialchars($role_name), $this->translate('role_already_exist_message')));

            return;
        }

        if ($db_manager->createRole($role_name, $priority)) {
            $this->bot->sendMessage($chat_id, str_replace(['{roleName}', '{priority}'], [htmlspecialchars($role_name), $priority], $this->translate('create_role_message')));
        } else {
            $this->bot->sendMessage($chat_id, $this->translate('create_role_failure_message'));
        }
    }
}
