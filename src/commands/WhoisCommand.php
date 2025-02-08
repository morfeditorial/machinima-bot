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
use morfeditorial\AbstractCommand;

class WhoisCommand extends AbstractCommand
{
    public function __construct(MyBot $bot)
    {
        parent::__construct($bot);
        $this->setAliases(['whois']);
        $this->setHiddenFromMenu(true);
    }

    public function getDescriptionKey() : string
    {
        return 'whois_command_description';
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
        if (! isset($args[0])) {
            $this->bot->sendMessage($chat_id, $this->translate('whois_usage_message'));

            return;
        }

        $whois = json_decode(file_get_contents('http://ip-api.com/json/' . $args[0]), true);

        if ('success' !== $whois['status']) {
            $this->bot->sendMessage($chat_id, $this->translate('whois_no_response_message'));

            return;
        }

        $this->bot->sendMessage($chat_id, str_replace(['{ip_address}', '{query}', '{country}', '{country_code}', '{region}', '{city}', '{timezone}', '{lat}', '{lon}', '{org}', '{isp}'], [strtolower($args[0]), $whois['query'], $whois['country'], $whois['countryCode'], $whois['regionName'], $whois['city'], $whois['timezone'], $whois['lat'], $whois['lon'], $whois['org'], $whois['isp']], $this->translate('whois_info_message')));
    }
}
