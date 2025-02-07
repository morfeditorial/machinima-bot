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
use morfeditorial\DependencyContainer;

class CreateRoleCommand extends AbstractCommand
{
    private $db_manager;

    public function __construct(MyBot $bot, DependencyContainer $container)
    {
        parent::__construct($bot, $container);
        $this->setDescription($this->translator->translate($this->getDescriptionKey()));
        $this->setAliases(['create_role']);
        $this->setHiddenFromMenu(true);

        $this->db_manager = $container->get('db_manager');
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
        if (! $this->db_manager->hasHigherRole($user_id, 'admin')) {
            $this->bot->sendMessage($chat_id, $this->translator->translate('no_permission_message'));

            return;
        }

        if (2 > count($args)) {
            $this->bot->sendMessage($chat_id, $this->translator->translate('create_role_usage_message'));

            return;
        }

        $role_name = $args[0];
        $priority = intval($args[1]);

        if ($this->db_manager->getRoleByName($role_name)) {
            $this->bot->sendMessage($chat_id, str_replace('{roleName}', htmlspecialchars($role_name), $this->translator->translate('role_already_exist_message')));

            return;
        }

        if ($this->db_manager->createRole($role_name, $priority)) {
            $this->bot->sendMessage($chat_id, str_replace(['{roleName}', '{priority}'], [htmlspecialchars($role_name), $priority], $this->translator->translate('create_role_message')));
        } else {
            $this->bot->sendMessage($chat_id, $this->translator->translate('create_role_failure_message'));
        }
    }
}
