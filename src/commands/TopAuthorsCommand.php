<?php

/**
 * @link https://cssm.pp.ua/
 */

declare(strict_types=1);

namespace morfeditorial\commands;

use morfeditorial\AbstractCommand;
use morfeditorial\MyBot;

class TopAuthorsCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['top_authors']);
    }

    public function getDescriptionKey() : string
    {
        return 'top_authors_command_description';
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
        $this->getUserStateService()->clearState($user_id);

        $authorService = $this->bot->getContainer()->get('author_service');
        $topAuthors = $authorService->getTopAuthors(10);

        $keyboard = ['inline_keyboard' => []];

        foreach ($topAuthors as $author) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $author['name'] . ' (' . $author['projects_count'] . ' 🎬)', 'callback_data' => 'author:profile:' . $author['id']],
            ];
        }

        $messageText = empty($topAuthors)
            ? $this->translate('empty_authors_list_message')
            : $this->translate('top_authors');

        $this->bot->sendMessage($chat_id, $messageText, $keyboard);
    }
}
