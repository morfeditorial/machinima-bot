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
        $this->getUserStateService()->clearState($user_id);
        $contentService = $this->bot->getContainer()->get('content_service');
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $keyboard = ['inline_keyboard' => []];

        $categories = $contentService->getCategoriesByParent(null);
        foreach ($categories as $cat) {
            $keyboard['inline_keyboard'][] = [
                ['text' => '📁 ' . $cat['name'], 'callback_data' => 'public:category:' . $cat['id']],
            ];
        }

        if (!is_null($current_panel)) {
            $this->bot->deleteMessage($chat_id, $current_panel);
        }

        $this->getUserService()->setCurrentPanel($user_id, $message_id + 1);
        $this->bot->pictureReply($chat_id, $this->translate('categories'), $visualsLinks[1], $keyboard);
    }
}
