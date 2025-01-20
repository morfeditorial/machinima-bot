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

    private array $commands = [];

    public function __construct(MyBot $bot, DependencyContainer $container)
    {
        $this->bot = $bot;
        $this->container = $container;
    }

    public function registerCommand(CommandInterface $command) : void
    {
        $this->commands[] = $command;
    }

    public function initializeCommands() : void
    {
        $translator = $this->container->get('translator');

        foreach ($translator->getAvailableLocales() as $locale) {
            $commands = [];
            foreach ($this->commands as $command) {
                if ($command instanceof AbstractCommand) {
                    $translator->setUserLocale($locale);
                    $command->setDescription($translator->translate($command->getDescriptionKey()));
                    $aliases = $translator->translate($command->getAliasesKey());
                    if (is_array($aliases)) {
                        $command->setAliases($aliases);
                    } else {
                        $command->setAliases([$aliases]);
                    }
                    $commands[] = [
                        'command' => $command->getAliases()[0],
                        'description' => $command->getDescription(),
                    ];
                }
            }
            $this->bot->setCommands(json_encode($commands, JSON_UNESCAPED_UNICODE), null, $locale);
        }
    }

    public function create(string $commandName) : ?CommandInterface
    {
        foreach ($this->commands as $command) {
            if (in_array($commandName, $command->getAliases())) {
                return $command;
            }
        }

        return null;
    }

    public function create(string $commandName) : ?CommandInterface
    {
        return match ($commandName) {
            'start' => new \morfeditorial\commands\StartCommand($this->bot, $this->container),
            'help' => new \morfeditorial\commands\HelpCommand($this->bot, $this->container),
            'time' => new \morfeditorial\commands\TimeCommand($this->bot, $this->container),
            'weather' => new \morfeditorial\commands\WeatherCommand($this->bot, $this->container),
            'whois' => new \morfeditorial\commands\WhoisCommand($this->bot, $this->container),
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
