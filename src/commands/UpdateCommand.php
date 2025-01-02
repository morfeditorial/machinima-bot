<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \\/       \\//       \//       \
 *  /        //        ///        //      __/
 * /         /         /        _/        _/
 * \__/__/__/\________/\____/___/\_______/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author CSSM Group
 * @link https://cssm.pp.ua/
 *
 *
 */

declare(strict_types=1);

namespace morfeditorial\commands;

use morfeditorial\MyBot;
use morfeditorial\CommandInterface;
use morfeditorial\DependencyContainer;

class UpdateCommand implements CommandInterface
{
    private MyBot $bot;

    private $dbManager;

    private $translator;

    private $visualsLinks;

    public function __construct(MyBot $bot, DependencyContainer $container)
    {
        $this->bot = $bot;
        $this->dbManager = $container->get('dbManager');
        $this->translator = $container->get('translator');
        $this->visualsLinks = $container->get('visualsLinks');
    }

    public function execute(
        string $message,
        int $messageId,
        string $chatType,
        int $chatId,
        int $userId,
        $payload,
        ?int $replyMessageId,
        ?int $replyAuthor,
        string $firstName,
        $currentPanel,
        $currentPage,
        string $cmd,
        array $args
    ) : void {
        if (! $this->dbManager->hasHigherRole($userId, "admin")) {
            $this->bot->sendMessage($chatId, $this->translator->translate("no_permission_message"));
            return;
        }

        $this->bot->setCommands(json_encode([
            ["command" => "start", "description" => "Start communication with the assistant"],
            ["command" => "help", "description" => "Learn more about the bot"],
            ["command" => "search_content", "description" => "Find content by tag"],
            ["command" => "search_author", "description" => "Find information about the author"],
            ["command" => "categories", "description" => "View available categories"],
            ["command" => "top_authors", "description" => "View the most popular authors"],
            ["command" => "random_content", "description" => "Get random content"]
        ], JSON_UNESCAPED_UNICODE));

        $this->bot->setCommands(json_encode([
            ["command" => "start", "description" => "Rozpocznij komunikację z asystentem"],
            ["command" => "help", "description" => "Dowiedz się więcej o bocie"],
            ["command" => "search_content", "description" => "Znajdź zawartość według tagu"],
            ["command" => "search_author", "description" => "Znajdź informacje o autorze"],
            ["command" => "categories", "description" => "Zobacz dostępne kategorie"],
            ["command" => "top_authors", "description" => "Zobacz najpopularniejszych autorów"],
            ["command" => "random_content", "description" => "Zdobądź losową zawartość"]
        ], JSON_UNESCAPED_UNICODE), null, "pl");

        $this->bot->setCommands(json_encode([
            ["command" => "start", "description" => "Начать общение с помощником"],
            ["command" => "help", "description" => "Узнать больше о боте"],
            ["command" => "search_content", "description" => "Найти контент по тегам"],
            ["command" => "search_author", "description" => "Найти информацию об авторе"],
            ["command" => "categories", "description" => "Просмотреть доступные категории"],
            ["command" => "top_authors", "description" => "Посмотреть самых популярных авторов"],
            ["command" => "random_content", "description" => "Получить случайный контент"]
        ], JSON_UNESCAPED_UNICODE), null, "ru");

        $this->bot->setCommands(json_encode([
            ["command" => "start", "description" => "Пачніце зносіны з памочнікам"],
            ["command" => "help", "description" => "Даведайцеся больш пра бота"],
            ["command" => "search_content", "description" => "Знайдзіце кантэнт па тэгу"],
            ["command" => "search_author", "description" => "Знайдзіце звесткі пра аўтара"],
            ["command" => "categories", "description" => "Прагляд даступных катэгорый"],
            ["command" => "top_authors", "description" => "Прагляд самых папулярных аўтараў"],
            ["command" => "random_content", "description" => "Атрымаць выпадковы кантэнт"]
        ], JSON_UNESCAPED_UNICODE), null, "be");

        $this->bot->setCommands(json_encode([
            ["command" => "start", "description" => "Почати спілкування з помічником"],
            ["command" => "help", "description" => "Дізнатися більше про бота"],
            ["command" => "search_content", "description" => "Знайти контент за тегами"],
            ["command" => "search_author", "description" => "Знайти інформацію про автора"],
            ["command" => "categories", "description" => "Переглянути доступні категорії"],
            ["command" => "top_authors", "description" => "Переглянути найпопулярніших авторів"],
            ["command" => "random_content", "description" => "Отримати випадковий контент"]
        ], JSON_UNESCAPED_UNICODE), null, "uk");

        $this->bot->setMyName("MORF — centralized chatbot", "en");
        $this->bot->setMyName("MORF — scentralizowany chatbot", "pl");
        $this->bot->setMyName("MORF — централизованный чат-бот", "ru");
        $this->bot->setMyName("MORF — цэнтралізаваны чат-бот", "be");
        $this->bot->setMyName("MORF — централізований чат‐бот", "uk");

        $this->bot->setMyDescription("Hello, I will help you discover interesting projects, short films, and series created based on Minecraft. You won't be bored with me!\n\nMy purpose is to centralize Ukrainian machinimators in one list.", "en");
        $this->bot->setMyDescription("Cześć, pomogę Ci odkryć interesujące projekty, krótkie filmy i seriale stworzone na podstawie Minecrafta. Z nami nie będziesz się nudzić!\n\nMoim celem jest skupienie ukraińskich machinimatorów w jednym miejscu.", "pl");
        $this->bot->setMyDescription("Привет, я помогу тебе найти интересные проекты, короткометражные фильмы и сериалы, снятые на основе Minecraft. Со мной тебе не будет скучно!\n\nМоя цель — централизовать украинских машиниматоров в одном списке.", "ru");
        $this->bot->setMyDescription("Прывітанне, я дапаможу табе знайсці цікавыя праекты, кароткаметражныя фільмы і серыялы, знятыя на аснове Minecraft. За мной табе не будзе нудна!\n\nМой мэт — цэнтралізаваць украінскіх машыніматараў у адным спісе.", "be");
        $this->bot->setMyDescription("Привіт, я допоможу тобі знайти цікаві проєкти, короткометражні фільми і серіали, зняті на основі Minecraft. Зі мною тобі не буде нудно!\n\nМоє покликання — централізувати українських машиніматорів в одному списку.", "uk");

        $this->bot->setMyShortDescription("A bot that helps you discover interesting projects, short films, and series created based on Minecraft.", "en");
        $this->bot->setMyShortDescription("Bot, który pomoże Ci odkryć interesujące projekty, krótkie filmy i seriale stworzone na podstawie Minecrafta.", "pl");
        $this->bot->setMyShortDescription("Бот, который поможет вам найти интересные проекты, короткометражные фильмы и сериалы, созданные на основе Minecraft.", "ru");
        $this->bot->setMyShortDescription("Бот, які дапаможа табе знайсці цікавыя праекты, кароткаметражныя фільмы і серыялы, знятыя на аснове Minecraft.", "be");
        $this->bot->setMyShortDescription("Бот, який допоможе тобі знайти цікаві проєкти, короткометражні фільми і серіали, зняті на основі Minecraft.", "uk");

        $this->bot->sendMessage($chatId, $this->translator->translate("update_message"));
    }
}
