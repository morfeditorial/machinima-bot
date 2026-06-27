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

class StartCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['start', 'begin', 'initiate']);
    }

    public function getDescriptionKey() : string
    {
        return 'start_command_description';
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
        $this->getUserStateService()->clearState($user_id);

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translate('search_content'), 'callback_data' => 'public:search'],
                ],
                [
                    ['text' => $this->translate('categories'), 'callback_data' => 'public:categories'],
                ],
                [
                    ['text' => $this->translate('top_authors'), 'callback_data' => 'public:top_authors'],
                ],
                [
                    ['text' => $this->translate('random_content'), 'callback_data' => 'public:random'],
                ],
            ],
        ];

        if ($this->bot->isGranted('moderator')) {
            $keyboard['inline_keyboard'][] = [
                ['text' => '⚙️ Admin Panel', 'callback_data' => 'admin:panel'],
            ];
        }

        $this->bot->sendMessage($chat_id, $this->translate('welcome_message'), $keyboard);
    }
}
