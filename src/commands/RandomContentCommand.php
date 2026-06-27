<?php

/**
 * @link https://cssm.pp.ua/
 */

declare(strict_types=1);

namespace morfeditorial\commands;

use morfeditorial\AbstractCommand;
use morfeditorial\MyBot;

class RandomContentCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['random_content', 'random']);
    }

    public function getDescriptionKey() : string
    {
        return 'random_content_command_description';
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
        $contentService = $this->bot->getContainer()->get('content_service');
        $randomContent = $contentService->getRandomContent();

        if ($randomContent) {
            if (!is_null($current_panel)) {
                $this->bot->deleteMessage($chat_id, $current_panel);
            }
            $this->getUserService()->setCurrentPanel($user_id, $message_id + 1);

            $screenClass = \morfeditorial\screens\Project\ProjectViewScreen::class;
            $screen = new $screenClass($this->bot, ['chat_id' => $chat_id, 'user_id' => $user_id]);
            $screen->handleCallback('view', [(string)$randomContent['id']]);
        } else {
            $this->bot->sendMessage($chat_id, $this->translate('no_content_found'));
        }
    }
}
