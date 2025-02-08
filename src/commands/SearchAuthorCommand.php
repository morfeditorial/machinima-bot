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

class SearchAuthorCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['search_author']);
    }

    public function getDescriptionKey() : string
    {
        return 'search_author_command_description';
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
        if (empty($args)) {
            $this->bot->sendMessage($chat_id, $this->translate('search_author_usage_message'));

            return;
        }

        $authors = $this->getDbManager()->getAllAuthors();
        $search_query = implode(' ', $args);
        $results = $this->getSearch()->fuzzySearch($search_query, $authors);

        if (empty($results)) {
            $this->bot->sendMessage($chat_id, str_replace('{searchQuery}', htmlspecialchars($search_query), $this->translate('no_search_results_message')));

            return;
        }

        $this->bot->sendButton($chat_id, str_replace(['{searchQuery}', '{count}'], [htmlspecialchars($search_query), count($results)], $this->translate('search_author_message')), $this->bot->generateAuthorsKeyboard(1, 6, 1, 'profile_author_', 'search_author_' . $search_query, $results));
    }
}
