<?php

/**
 * @link https://cssm.pp.ua/
 */

declare(strict_types=1);

namespace morfeditorial\screens\Public;

use morfeditorial\screens\AbstractScreen;

class SearchContentScreen extends AbstractScreen
{
    public function render() : void
    {
        $this->bot->getUserStateService()->setState($this->userId, [], 'awaiting_search_query');

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('cancel'), 'callback_data' => 'public:cancel'],
                ],
            ],
        ];

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[1],
            $this->translate('enter_search_query'),
            $keyboard
        );
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('cancel' === $action) {
            $this->bot->getUserStateService()->clearState($this->userId);
            // Go back to main menu or something
            $screenClass = \morfeditorial\screens\Public\MainMenuScreen::class;
            $screen = new $screenClass($this->bot, ["chat_id" => $this->chatId, "user_id" => $this->userId]);
            $screen->render();
        }
    }

    public function handleMessage(string $text) : void
    {
        if ('awaiting_search_query' === $this->bot->getUserStateService()->getState($this->userId)) {
            $this->bot->getUserStateService()->clearState($this->userId);
            $this->doSearch($text);
        }
    }

    private function doSearch(string $query) : void
    {
        $contentService = $this->bot->getContainer()->get('content_service');
        $results = $contentService->searchContent($query);

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $keyboard = ['inline_keyboard' => []];

        foreach ($results as $item) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $item['title'], 'callback_data' => 'project:view:' . $item['id']],
            ];
        }

        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate('go_back'), 'callback_data' => 'public:main'],
        ];

        $message = empty($results) ? $this->translate('no_search_results') : $this->translate('search_results');

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[1],
            $message,
            $keyboard
        );
    }
}
