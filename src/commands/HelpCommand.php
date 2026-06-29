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
use Morfeditorial\TelegramBotBundle\Client\TelegramClient;

class HelpCommand extends BaseMachinimaCommand
{
    public function __construct(TelegramClient $client)
    {
        parent::__construct($client);
        $this->setAliases(['help']);
    }

    public function getCommand() : string
    {
        return 'help';
    }

    public function getDescriptionKey() : string
    {
        return 'help_command_description';
    }

    public function handle(array $update) : void
    {
        $chatId = $update['message']['chat']['id'] ?? 0;
        if (!$chatId) {
            return;
        }

        $this->client->sendMessage($chatId, $this->translate('help_message'));
    }
}
