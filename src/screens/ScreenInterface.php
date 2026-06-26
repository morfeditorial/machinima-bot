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

namespace morfeditorial\screens;

interface ScreenInterface
{
    /** Відображення екрана (відправка/редагування повідомлення) */
    public function render() : void;

    /** Обробка натискання кнопки саме на цьому екрані */
    public function handleCallback(string $action, array $params) : void;

    /** Обробка текстового повідомлення, якщо екран чекає вводу */
    public function handleMessage(string $text) : void;
}
