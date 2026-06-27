<?php

/**
 * @link https://cssm.pp.ua/
 */

declare(strict_types=1);

namespace morfeditorial\screens\Public;

use morfeditorial\screens\AbstractScreen;

class MainMenuScreen extends AbstractScreen
{
    public function render() : void
    {
        $this->bot->getUserStateService()->clearState($this->userId);

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('search_content'), 'callback_data' => 'public:search'],
                ],
                [
                    ['text' => $this->translate('categories'), 'callback_data' => 'public:categories'],
                ],
                [
                    ['text' => $this->translate('top_authors'), 'callback_data' => 'public:top_authors'],
                ],
                [
                    ['text' => $this->translate('random_content'), 'callback_data' => 'public:random'],
                ],
            ],
        ];

        if ($this->isGranted('moderator')) {
            $keyboard['inline_keyboard'][] = [
                ['text' => '⚙️ Admin Panel', 'callback_data' => 'admin:panel'],
            ];
        }

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[0],
            $this->translate('welcome_message'),
            $keyboard
        );
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('search' === $action) {
            $screenClass = \morfeditorial\screens\Public\SearchContentScreen::class;
            $screen = new $screenClass($this->bot, ["chat_id" => $this->chatId, "user_id" => $this->userId]);
            $screen->render();
        } elseif ('main' === $action || 'cancel' === $action) {
            $this->render();
        } elseif ('categories' === $action) {
            $screenClass = \morfeditorial\screens\Public\CategoryListScreen::class;
            $screen = new $screenClass($this->bot, ["chat_id" => $this->chatId, "user_id" => $this->userId]);
            $screen->render();
        } elseif ('top_authors' === $action) {
            $screenClass = \morfeditorial\screens\Public\TopAuthorsScreen::class;
            $screen = new $screenClass($this->bot, ["chat_id" => $this->chatId, "user_id" => $this->userId]);
            $screen->render();
        } elseif ('random' === $action) {
            $contentService = $this->bot->getContainer()->get('content_service');
            $randomContent = $contentService->getRandomContent();

            if ($randomContent) {
                $this->sendProjectMessage($randomContent, $contentService);
            } else {
                $this->bot->sendMessage($this->chatId, $this->translate('no_content_found'));
            }
        } elseif ('view' === $action) {
            $contentService = $this->bot->getContainer()->get('content_service');
            $projectId = (int)($params[0] ?? 0);
            $project = $contentService->getContentById($projectId);

            if ($project && 'published' === $project['status']) {
                $this->sendProjectMessage($project, $contentService);
            } else {
                $this->bot->sendMessage($this->chatId, $this->translate('no_content_found'));
            }
        }
    }

    private function sendProjectMessage(array $project, $contentService) : void
    {
        $staff = $contentService->getStaffByContentId((int)$project['id']);
        $staff_text = "";
        foreach ($staff as $member) {
            $staff_text .= "\n- " . htmlspecialchars($member['author_name']) . " (" . htmlspecialchars($member['role']) . ")";
        }

        $categories = $contentService->getCategoriesByContentId((int)$project['id']);
        $categories_names = array_column($categories, 'name');
        $categories_text = !empty($categories_names) ? implode(', ', $categories_names) : "\u{2014}";

        $message_text = "📦 <b>" . htmlspecialchars($project['title']) . "</b>\n";
        $message_text .= "📝 " . htmlspecialchars($project['description'] ?? '') . "\n";
        $message_text .= "🏷 Категорії: " . htmlspecialchars($categories_text) . "\n";
        if (!empty($project['url'])) {
            $message_text .= "🔗 Посилання: " . htmlspecialchars($project['url']) . "\n";
        }
        $message_text .= "\n👥 Команда:" . ($staff_text ?: " \u{2014}");

        $this->bot->sendMessage($this->chatId, $message_text);
    }

    public function handleMessage(string $text) : void {}
}
