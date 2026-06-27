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
            $screen = new $screenClass($this->bot, $this->chatId, $this->userId);
            $screen->render();
        } elseif ('main' === $action || 'cancel' === $action) {
            $this->render();
        } elseif ('categories' === $action) {
            $screenClass = \morfeditorial\screens\Public\CategoryListScreen::class;
            $screen = new $screenClass($this->bot, $this->chatId, $this->userId);
            $screen->render();
        } elseif ('top_authors' === $action) {
            $screenClass = \morfeditorial\screens\Public\TopAuthorsScreen::class;
            $screen = new $screenClass($this->bot, $this->chatId, $this->userId);
            $screen->render();
        } elseif ('random' === $action) {
            // Re-use RandomContentCommand logic
            $contentService = $this->bot->getContainer()->get('content_service');
            $randomContent = $contentService->getRandomContent();

            if ($randomContent) {
                $screenClass = \morfeditorial\screens\Project\ProjectViewScreen::class;
                $screen = new $screenClass($this->bot, $this->chatId, $this->userId);
                $screen->handleCallback('view', [(string)$randomContent['id']]);
            } else {
                $this->bot->sendMessage($this->chatId, $this->translate('no_content_found'));
            }
        }
    }

    public function handleMessage(string $text) : void {}
}
