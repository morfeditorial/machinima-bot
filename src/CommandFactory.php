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

    public function __construct(MyBot $bot, DependencyContainer &$container)
    {
        $this->bot = $bot;
        $this->container = $container;
    }

    public function registerCommand(CommandInterface $command) : void
    {
        $command->setContainer($this->container);
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
                    $aliases = $command->getAliases();
                    if (is_array($aliases)) {
                        $command->setAliases($aliases);
                    } else {
                        $command->setAliases([$aliases]);
                    }
                    if ($command->isHiddenFromMenu()) {
                        continue;
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

    public function create(string $command_name) : ?CommandInterface
    {
        foreach ($this->commands as $command) {
            if (in_array($command_name, $command->getAliases())) {
                return $command;
            }
        }

        return null;
    }
}
