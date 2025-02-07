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

class AssignInitialAdminCommand extends AbstractCommand
{
    private $db_manager;

    public function __construct(MyBot $bot, DependencyContainer $container)
    {
        parent::__construct($bot, $container);
        $this->setDescription($this->translator->translate($this->getDescriptionKey()));
        $this->setAliases(['assign_initial_admin']);
        $this->setHiddenFromMenu(true);

        $this->db_manager = $container->get('db_manager');
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
        if (! $this->db_manager->getRoleByName('admin')) {
            $this->db_manager->createRole('admin', 100);
        }

        if (0 < $this->db_manager->getUsersCountByRole('admin')) {
            $this->bot->sendMessage($chat_id, $this->translator->translate('already_initialled_admin_message'));

            return;
        }

        $this->db_manager->assignRole($user_id, 'admin');

        $this->bot->sendMessage($chat_id, $this->translator->translate('success_initialled_admin_message'));
    }
}
