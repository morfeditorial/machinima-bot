<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \/       \//       \//       \
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

namespace morfeditorial\screens\Main;

use morfeditorial\screens\AbstractScreen;

class MainMenuScreen extends AbstractScreen
{
    public function render() : void
    {
        $text = "🏠 <b>" . $this->translate('main_menu_title') . "</b>\n\n" .
                $this->translate('main_menu_description');

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '👤 ' . $this->translate('authors'), 'callback_data' => 'author:list'],
                    ['text' => '📦 ' . $this->translate('projects'), 'callback_data' => 'project:list']
                ],
                [
                    ['text' => '⚙️ ' . $this->translate('settings'), 'callback_data' => 'settings:menu']
                ]
            ]
        ];

        // Тут логіка відправки чи редагування повідомлення залежно від контексту
        // Для прикладу, використаємо уявний метод
        // $this->bot->editMediaMessage($this->chatId, ..., $text, $keyboard);
    }

    public function handleCallback(string $action, array $params) : void
    {
        // Якщо цей екран викликали напряму як 'main:menu'
        // То дія 'menu' просто має відмалювати його
        if ('menu' === $action) {
            $this->render();
        }
    }

    public function handleMessage(string $text) : void
    {
        // Якщо користувач щось написав в головному меню, можемо або
        // показати меню ще раз, або просто ігнорувати
        $this->render();
    }
}
