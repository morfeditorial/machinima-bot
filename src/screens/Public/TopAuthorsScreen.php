<?php

/**
 * @link https://cssm.pp.ua/
 */

declare(strict_types=1);

namespace morfeditorial\screens\Public;

use morfeditorial\screens\AbstractScreen;

class TopAuthorsScreen extends AbstractScreen
{
    public function render() : void
    {
        $this->bot->getUserStateService()->clearState($this->userId);

        $authorService = $this->bot->getContainer()->get('author_service');
        $topAuthors = $authorService->getTopAuthors(10);

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $keyboard = ['inline_keyboard' => []];

        foreach ($topAuthors as $author) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $author['name'] . ' (' . $author['projects_count'] . ' 🎬)', 'callback_data' => 'author:profile:' . $author['id']],
            ];
        }

        $keyboard['inline_keyboard'][] = [
            ['text' => $this->translate('go_back'), 'callback_data' => 'public:main'],
        ];

        $message = empty($topAuthors) ? $this->translate('empty_authors_list_message') : $this->translate('top_authors');

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[1],
            $message,
            $keyboard
        );
    }

    public function handleCallback(string $action, array $params) : void {}

    public function handleMessage(string $text) : void {}
}
