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
        if (! $this->isGranted('admin')) {
            $this->bot->sendMessage($chat_id, $this->translate('no_permission_message'));

            return;
        }

        if (count($args) < 2) {
            $this->bot->sendMessage($chat_id, $this->translate('assign_role_usage_message'));

            return;
        }

        $target_user_id = intval($args[0]);
        $role_name = $args[1];

        if (! $this->getRoleService()->getRoleByName($role_name)) {
            $this->bot->sendMessage($chat_id, str_replace('{roleName}', htmlspecialchars($role_name), $this->translate('role_not_found_message')));

            return;
        }

        if ($this->getRoleService()->assignRole($target_user_id, $role_name)) {
            $this->bot->sendMessage($chat_id, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('assign_role_message')));
        } else {
            $this->bot->sendMessage($chat_id, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('role_assignment_failure_message')));
        }
    }
}
