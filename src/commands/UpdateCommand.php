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
        if (! $this->db_manager->hasHigherRole($user_id, "admin")) {
            $this->bot->sendMessage($chat_id, $this->translate("no_permission_message"));
            return;
        }

        $this->bot->setMyName("MORF — centralized chatbot", "en");
        $this->bot->setMyName("MORF — scentralizowany chatbot", "pl");
        $this->bot->setMyName("MORF — цэнтралізаваны чат-бот", "be");
        $this->bot->setMyName("MORF — централізований чат‐бот", "uk");

        $this->bot->setMyDescription("Hello, I will help you discover interesting projects, short films, and series created based on Minecraft. You won't be bored with me!\n\nMy purpose is to centralize Ukrainian machinimators in one list.", "en");
        $this->bot->setMyDescription("Cześć, pomogę Ci odkryć interesujące projekty, krótkie filmy i seriale stworzone na podstawie Minecrafta. Z nami nie będziesz się nudzić!\n\nMoim celem jest skupienie ukraińskich machinimatorów w jednym miejscu.", "pl");
        $this->bot->setMyDescription("Прывітанне, я дапаможу табе знайсці цікавыя праекты, кароткаметражныя фільмы і серыялы, знятыя на аснове Minecraft. За мной табе не будзе нудна!\n\nМой мэт — цэнтралізаваць украінскіх машыніматараў у адным спісе.", "be");
        $this->bot->setMyDescription("Привіт, я допоможу тобі знайти цікаві проєкти, короткометражні фільми і серіали, зняті на основі Minecraft. Зі мною тобі не буде нудно!\n\nМоє покликання — централізувати українських машиніматорів в одному списку.", "uk");

        $this->bot->setMyShortDescription("A bot that helps you discover interesting projects, short films, and series created based on Minecraft.", "en");
        $this->bot->setMyShortDescription("Bot, który pomoże Ci odkryć interesujące projekty, krótkie filmy i seriale stworzone na podstawie Minecrafta.", "pl");
        $this->bot->setMyShortDescription("Бот, які дапаможа табе знайсці цікавыя праекты, кароткаметражныя фільмы і серыялы, знятыя на аснове Minecraft.", "be");
        $this->bot->setMyShortDescription("Бот, який допоможе тобі знайти цікаві проєкти, короткометражні фільми і серіали, зняті на основі Minecraft.", "uk");

        $this->bot->sendMessage($chat_id, $this->translate("update_message"));
    }
}
