<?php

/**
 * @link https://cssm.pp.ua/
 */

declare(strict_types=1);

namespace morfeditorial\commands;

use morfeditorial\AbstractCommand;
use morfeditorial\MyBot;

class SearchContentCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['search_content', 'search']);
    }

    public function getDescriptionKey() : string
    {
        return 'search_content_command_description';
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
        $this->getUserStateService()->setState($user_id, [], 'awaiting_search_query');

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('cancel'), 'callback_data' => 'public:cancel'],
                ],
            ],
        ];

        $this->bot->sendMessage($chat_id, $this->translate('enter_search_query'), $keyboard);
    }
}
