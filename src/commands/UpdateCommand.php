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

namespace Morfeditorial\commands;

use Morfeditorial\BaseMachinimaCommand;
use Morfeditorial\TelegramBotBundle\Client\TelegramClient;

class UpdateCommand extends BaseMachinimaCommand
{
    public function __construct(TelegramClient $client)
    {
        parent::__construct($client);
        $this->setAliases(['update']);
        $this->setHiddenFromMenu(true);
    }

    public function getCommand() : string
    {
        return 'update';
    }

    public function getDescriptionKey() : string
    {
        return 'update_command_description';
    }

    public function handle(array $update) : void
    {
        $chatId = $update['message']['chat']['id'] ?? 0;
        if (!$chatId) {
            return;
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->client->sendMessage($chatId, $this->translate("no_permission_message"));
            return;
        }

        $translator = $this->getTranslator();

        foreach ($translator->getAvailableLocales() as $locale) {
            // Using generic request for advanced Telegram bot API methods
            $this->client->request('setMyName', [
                'name' => $translator->translateForLocale("bot_name", $locale),
                'language_code' => $locale
            ]);
            $this->client->request('setMyDescription', [
                'description' => $translator->translateForLocale("bot_description", $locale),
                'language_code' => $locale
            ]);
            $this->client->request('setMyShortDescription', [
                'short_description' => $translator->translateForLocale("bot_short_description", $locale),
                'language_code' => $locale
            ]);
        }

        $this->client->sendMessage($chatId, $this->translate("update_message"));
    }
}
