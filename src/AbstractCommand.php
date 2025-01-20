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

    protected $translator;

    protected $visualsLinks;

    protected string $description;

    protected array $aliases;

    public function __construct(MyBot $bot, DependencyContainer $container)
    {
        $this->bot = $bot;
        $this->translator = $container->get('translator');
        $this->visualsLinks = $container->get('visualsLinks');
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

    abstract public function execute(
        string $message,
        int $messageId,
        string $chatType,
        int $chatId,
        int $userId,
        $payload,
        ?int $replyMessageId,
        ?int $replyAuthor,
        string $firstName,
        $currentPanel,
        $currentPage,
        string $cmd,
        array $args
    ) : void;
}
