<?php

/**
 * @link https://cssm.pp.ua/
 */

declare(strict_types=1);

namespace morfeditorial\commands;

use morfeditorial\AbstractCommand;
use morfeditorial\MyBot;

class CategoriesCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['categories']);
    }

    public function getDescriptionKey() : string
    {
        return 'categories_command_description';
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
        $screenClass = \morfeditorial\screens\Public\CategoryListScreen::class;
        $screen = new $screenClass($this->bot, $chat_id, $user_id);
        $screen->render();
    }
}
