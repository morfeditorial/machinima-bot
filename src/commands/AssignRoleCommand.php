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

class AssignRoleCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['assign_role']);
        $this->setHiddenFromMenu(true);
    }

    public function getDescriptionKey() : string
    {
        return 'assign_role_command_description';
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
        if (! $this->db_manager->hasHigherRole($user_id, 'admin')) {
            $this->bot->sendMessage($chat_id, $this->translator->translate('no_permission_message'));

            return;
        }

        if (2 > count($args)) {
            $this->bot->sendMessage($chat_id, $this->translator->translate('assign_role_usage_message'));

            return;
        }

        $target_user_id = intval($args[0]);
        $role_name = $args[1];

        if (! $this->db_manager->getRoleByName($role_name)) {
            $this->bot->sendMessage($chat_id, str_replace('{roleName}', htmlspecialchars($role_name), $this->translator->translate('role_not_found_message')));

            return;
        }

        if ($this->db_manager->assignRole($target_user_id, $role_name)) {
            $this->bot->sendMessage($chat_id, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translator->translate('assign_role_message')));
        } else {
            $this->bot->sendMessage($chat_id, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translator->translate('role_assignment_failure_message')));
        }
    }
}
