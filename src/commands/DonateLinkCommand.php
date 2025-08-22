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
 * Copyright (c) 2024 Sergiy Chernega
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

class DonateLinkCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['donate_link']);
        $this->setHiddenFromMenu(true);
    }

    public function getDescriptionKey() : string
    {
        return 'donate_link_command_description';
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
