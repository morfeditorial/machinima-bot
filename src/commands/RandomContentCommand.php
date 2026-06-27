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
            $staff = $contentService->getStaffByContentId((int)$randomContent['id']);
            $staff_text = "";
            foreach ($staff as $member) {
                $staff_text .= "\n- " . htmlspecialchars($member['author_name']) . " (" . htmlspecialchars($member['role']) . ")";
            }

            $categories = $contentService->getCategoriesByContentId((int)$randomContent['id']);
            $categories_names = array_column($categories, 'name');
            $categories_text = !empty($categories_names) ? implode(', ', $categories_names) : "\u{2014}";

            $message_text = "📦 <b>" . htmlspecialchars($randomContent['title']) . "</b>\n";
            $message_text .= "📝 " . htmlspecialchars($randomContent['description'] ?? '') . "\n";
            $message_text .= "🏷 Категорії: " . htmlspecialchars($categories_text) . "\n";
            if (!empty($randomContent['url'])) {
                $message_text .= "🔗 Посилання: " . htmlspecialchars($randomContent['url']) . "\n";
            }
            $message_text .= "\n👥 Команда:" . ($staff_text ?: " \u{2014}");

            $this->bot->sendMessage($chat_id, $message_text);
        } else {
            $this->bot->sendMessage($chat_id, $this->translate('no_content_found'));
        }
    }
}
