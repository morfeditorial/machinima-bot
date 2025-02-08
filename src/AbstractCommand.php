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

abstract class AbstractCommand implements CommandInterface
{
    protected MyBot $bot;

    public DependencyContainer $container;

    protected $translator;

    protected $db_manager;

    protected $search;

    protected $visuals_links;

    protected string $description;

    protected array $aliases;

    protected bool $hidden_from_menu = false;

    public function __construct(MyBot $bot)
    {
        $this->bot = $bot;
    }

    public function setContainer(DependencyContainer &$container) : void
    {
        $this->container = $container;
        $this->translator = $container->get('translator');
        $this->db_manager = $container->get('db_manager');
        $this->search = $container->get('fuzzy_search');
        $this->visuals_links = $container->get('visuals_links');
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
