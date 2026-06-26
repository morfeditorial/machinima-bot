<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \\/       \\//       \//       \
 *  /        //        ///        //      __/
 * /         /         /        _/        _/
 * \__/__/__/\________/\____/___/\_______/
 *
 * This program is licensed under the CSSM Unlimited License v2.0.
 * Copyright (c) 2024 Serhii Cherneha
 *
 * @author CSSM Group
 * @link https://cssm.pp.ua/
 *
 *
 */

declare(strict_types=1);

namespace morfeditorial\screens\Author;

use morfeditorial\screens\AbstractScreen;

class AuthorListScreen extends AbstractScreen
{
    public function render() : void
    {
        if (!$this->isGranted('moderator')) {
            $this->bot->sendMessage($this->chatId, $this->translate('no_permission_message'));
            return;
        }

        // Параметр сторінки приходить в $this->data['page'] (встановлюється в Диспетчері)
        $page = $this->data['page'] ?? 1;
        $this->bot->getUserService()->setCurrentPage($this->userId, 'author:list:' . $page);

        $currentPanel = $this->bot->getUserService()->getCurrentPanel($this->userId);
        $visualsLinks = $this->bot->getContainer()->get('visuals_links');

        // Ми продовжуємо використовувати generateAuthorsKeyboard, але згодом його теж можна перенести
        $keyboard = $this->bot->generateAuthorsKeyboard($page, 3, 1, 'author:profile:', 'author:list:');

        $this->bot->editMediaMessage(
            $this->chatId,
            $currentPanel,
            $visualsLinks[9],
            $this->translate('list_of_authors_message'),
            $keyboard
        );
    }

    public function handleCallback(string $action, array $params) : void
    {
        if ('list' === $action) {
            $this->data['page'] = (int)($params[0] ?? 1);
            $this->render();
        }
    }

    public function handleMessage(string $text) : void
    {
        // Не чекаємо на текст
    }
}
