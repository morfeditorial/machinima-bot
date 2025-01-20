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

use morfeditorial\AbstractCommand;
use morfeditorial\MyBot;
use morfeditorial\DependencyContainer;

class SearchAuthorCommand extends AbstractCommand
{
    private $dbManager;

    private $search;

    public function __construct(MyBot $bot, DependencyContainer $container)
    {
        parent::__construct($bot, $container);
        $this->setDescription($this->translator->translate($this->getDescriptionKey()));
        $this->setAliases($this->translator->translate($this->getAliasesKey()));

        $this->dbManager = $container->get('dbManager');
        $this->search = $container->get('fuzzySearch');
    }

    public function getDescriptionKey() : string
    {
        return 'search_author_command_description';
    }

    public function getAliasesKey() : string
    {
        return 'search_author_command_aliases';
    }

    public function execute(
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
    ) : void {
        if (empty($args)) {
            $this->bot->sendMessage($chatId, $this->translator->translate('search_author_usage_message'));

            return;
        }

        $authors = $this->dbManager->getAllAuthors();
        $searchQuery = implode(' ', $args);
        $results = $this->search->fuzzySearch($searchQuery, $authors);

        if (empty($results)) {
            $this->bot->sendMessage($chatId, str_replace('{searchQuery}', htmlspecialchars($searchQuery), $this->translator->translate('no_search_results_message')));

            return;
        }

        $this->bot->sendButton($chatId, str_replace(['{searchQuery}', '{count}'], [htmlspecialchars($searchQuery), count($results)], $this->translator->translate('search_author_message')), $this->bot->generateAuthorsKeyboard(1, 6, 1, 'profile_author_', 'search_author_' . $searchQuery, $results));
    }
}
