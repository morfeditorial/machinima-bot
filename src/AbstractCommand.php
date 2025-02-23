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

use morfeditorial\interfaces\CommandInterface;

abstract class AbstractCommand implements CommandInterface
{
    protected MyBot $bot;

    protected DependencyContainer $container;

    protected string $description = '';

    protected array $aliases = [];

    protected bool $hidden_from_menu = false;

    public function __construct(MyBot $bot)
    {
        $this->bot = $bot;
    }

    public function setContainer(DependencyContainer &$container) : void
    {
        $this->container = $container;
    }

    public function getTranslator()
    {
        return $this->container->get('translator');
    }

    public function translate(string $key)
    {
        return $this->getTranslator()->translate($key);
    }

    public function getDbManager()
    {
        return $this->container->get('db_manager');
    }

    public function getSearch()
    {
        return $this->container->get('fuzzy_search');
    }

    public function getVisualsLinks()
    {
        return $this->container->get('visuals_links');
    }

    public function setDescription(string $description) : void
    {
        $this->description = $description;
    }

    public function getDescription() : string
    {
        return $this->description;
    }

    public function setAliases(array $aliases) : void
    {
        $this->aliases = $aliases;
    }

    public function getAliases() : array
    {
        return $this->aliases;
    }

    abstract public function getDescriptionKey() : string;

    public function setHiddenFromMenu(bool $hidden_from_menu) : void
    {
        $this->hidden_from_menu = $hidden_from_menu;
    }

    public function isHiddenFromMenu() : bool
    {
        return $this->hidden_from_menu;
    }

    abstract public function execute(
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
    ) : void;
}
