<?php

/**
 * @author Roman Lakhtadyr
 *
 * @link https://cssm.pp.ua/
 */

declare(strict_types=1);

namespace morfeditorial\screens\Author;

use morfeditorial\screens\AbstractScreen;

class AuthorLinkTelegramScreen extends AbstractScreen
{
    public function render() : void
    {
        $authorId = (int)$this->data['author_id'];
        $authorService = $this->bot->getContainer()->get('author_service');
        $author = $authorService->getAuthorById($authorId);

        if (!$this->isGranted('admin')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }
        $this->bot->getUserStateService()->setState($this->userId, ['author_id' => $authorId], 'link_telegram');

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('go_back'), 'callback_data' => 'author:profile:' . $authorId],
                ],
            ],
        ];

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[3],
            $this->translate('pending_telegram_link'),
            $keyboard
        );
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('link_telegram' === $action) {
            $this->data['author_id'] = $params[0] ?? 0;
            $this->render();
        }
    }

    public function handleMessage(string $text) : void
    {
        $stateData = $this->bot->getUserStateService()->getState($this->userId, 'link_telegram');
        $authorId = (int)($stateData['author_id'] ?? 0);

        if (!$this->isGranted('admin') || 0 === $authorId) {
            $this->bot->getUserStateService()->clearState($this->userId, 'link_telegram');
            return;
        }

        $this->bot->deleteMessage($this->chatId, $this->data['message_id'] ?? 0);

        $telegramId = (int) trim($text);
        if ($telegramId <= 0) {
            $this->bot->getUserStateService()->clearState($this->userId, 'link_telegram');
            $this->data['author_id'] = $authorId;
            (new AuthorProfileScreen($this->bot, $this->data))->render();
            return;
        }

        $authorService = $this->bot->getContainer()->get('author_service');
        $existingAuthor = $authorService->getAuthorByTelegramId($telegramId);
        if (null !== $existingAuthor) {
            $this->bot->sendMessage($this->chatId, $this->translate('telegram_already_linked'));
            $this->bot->getUserStateService()->clearState($this->userId, 'link_telegram');
            $this->data['author_id'] = $authorId;
            (new AuthorProfileScreen($this->bot, $this->data))->render();
            return;
        }

        $authorService->setTelegramId($authorId, $telegramId);

        $this->bot->getUserStateService()->clearState($this->userId, 'link_telegram');

        $this->data['author_id'] = $authorId;
        (new AuthorProfileScreen($this->bot, $this->data))->render();
    }
}
