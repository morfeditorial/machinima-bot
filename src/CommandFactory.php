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

namespace morfeditorial;

class CommandFactory
{
    private MyBot $bot;

    private DependencyContainer $container;

    public function __construct(MyBot $bot, DependencyContainer $container)
    {
        $this->bot = $bot;
        $this->container = $container;
    }

    public function create(string $commandName) : ?CommandInterface
    {
        return match ($commandName) {
            'start' => new \morfeditorial\commands\StartCommand($this->bot, $this->container),
            'help' => new \morfeditorial\commands\HelpCommand($this->bot, $this->container),
            'time' => new \morfeditorial\commands\TimeCommand($this->bot, $this->container),
            'update' => new \morfeditorial\commands\UpdateCommand($this->bot, $this->container),
            'admin_panel' => new \morfeditorial\commands\AdminPanelCommand($this->bot, $this->container),
            'search_author' => new \morfeditorial\commands\SearchAuthorCommand($this->bot, $this->container),
            'create_role' => new \morfeditorial\commands\CreateRoleCommand($this->bot, $this->container),
            'assign_initial_admin' => new \morfeditorial\commands\AssignInitialAdminCommand($this->bot, $this->container),
            'assign_role' => new \morfeditorial\commands\AssignRoleCommand($this->bot, $this->container),
            default => null,
        };
    }
}
