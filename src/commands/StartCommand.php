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

namespace morfeditorial\commands;

use morfeditorial\BaseMachinimaCommand;
use morfeditorial\TelegramBotBundle\Client\TelegramClient;

class StartCommand extends BaseMachinimaCommand
{
    public function __construct(TelegramClient $client)
    {
        parent::__construct($client);
        $this->setAliases(['start', 'begin', 'initiate']);
    }

    public function getCommand() : string
    {
        return 'start'; // Диспетчер шукатиме саме цю команду
    }

    public function getDescriptionKey() : string
    {
        return 'start_command_description';
    }

    public function handle(array $update) : void
    {
        $chatId = $update['message']['chat']['id'] ?? 0;
        $userId = $update['message']['from']['id'] ?? 0;

        if (!$chatId || !$userId) {
            return;
        }

        $this->userStateRepo->clear($userId);

        $photoUrl = $this->getVisualsLinks()[0];

        // Використовуємо новий універсальний клієнт замість старого MyBot
        $this->client->sendPhoto($chatId, $photoUrl, [
            'caption' => $this->translate('welcome_message')
        ]);
    }
}
