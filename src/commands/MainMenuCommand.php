<?php

declare(strict_types=1);

namespace morfeditorial\commands;

use morfeditorial\AbstractCommand;
use morfeditorial\MyBot;
use morfeditorial\screens\Main\MainMenuScreen;

class MainMenuCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['menu']);
    }

    public function getDescriptionKey() : string
    {
        return 'main_menu_command_description';
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
        if (! is_null($current_panel)) {
            $this->bot->deleteMessage($chat_id, $current_panel);
        }

        $this->getUserService()->setCurrentPanel($user_id, $message_id + 1);

        if (! is_null($current_page)) {
            $this->getUserService()->resetCurrentPage($user_id);
        }

        $screen = new MainMenuScreen($this->bot, ['message_id' => $message_id, 'chat_id' => $chat_id]);
        $screen->render();
    }
}
