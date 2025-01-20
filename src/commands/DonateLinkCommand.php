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

use morfeditorial\AbstractCommand;
use morfeditorial\MyBot;
use morfeditorial\DependencyContainer;

class DonateLinkCommand extends AbstractCommand
{
    public function __construct(MyBot $bot, DependencyContainer $container)
    {
        parent::__construct($bot, $container);
        $this->setDescription($this->translator->translate($this->getDescriptionKey()));
        $this->setAliases(['donate_link']);
    }

    public function getDescriptionKey() : string
    {
        return 'donate_link_command_description';
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
        $keyboard = [
            "inline_keyboard" => [
                [
                    ["text" => "🇺🇦 Підтримати збір", "url" => "https://send.monobank.ua/jar/MT6kuUSCY"]
                ]
            ]
        ];

        $this->bot->sendMessage("-1001639937592", "Сьогодні — найскладніший понеділок за останні місяці 💔\n\nМи сподіваємося, що у вас і ваших близьких все гаразд.\n\nЗнаємо, що такі дні спустошують, але маємо залишатися стійкими та підтримувати постраждалих.\n\nБлагодійний фонд «Таблеточки» відкрив <a href='https://send.monobank.ua/jar/MT6kuUSCY'>естренний збір</a> для пацієнтів зруйнованої клініки «Охматдит».\n\nДолучайтеся донатом або репостом. Зараз кожна допомога є важливою 🙌", $keyboard, ["link_preview_options" => json_encode(["is_disabled" => false, "url" => "https://telegra.ph/file/47f01563992dbc6c48e9e.jpg"])]);
    }
}
