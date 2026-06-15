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

use morfeditorial\AbstractCommand;
use morfeditorial\MyBot;

class UpdateCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['update']);
        $this->setHiddenFromMenu(true);
    }

    public function getDescriptionKey() : string
    {
        return 'update_command_description';
    }

    public function execute(
        string $message,
        int $message_id,
        string $chat_type,
        int $chat_id,
        int $user_id,
        $payload,
        ?int $reply_message_id,
        ?int $reply_author,
        string $first_name,
        $current_panel,
        $current_page,
        string $cmd,
        array $args
    ) : void {
        if (! $this->getRoleService()->hasHigherRole($user_id, "admin")) {
            $this->bot->sendMessage($chat_id, $this->translate("no_permission_message"));
            return;
        }

        $translator = $this->getTranslator();

        foreach ($translator->getAvailableLocales() as $locale) {
            $this->bot->setMyName($translator->translateForLocale("bot_name", $locale), $locale);
            $this->bot->setMyDescription($translator->translateForLocale("bot_description", $locale), $locale);
            $this->bot->setMyShortDescription($translator->translateForLocale("bot_short_description", $locale), $locale);
        }

        $this->bot->sendMessage($chat_id, $this->translate("update_message"));
    }
}
