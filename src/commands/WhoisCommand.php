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

class WhoisCommand extends AbstractCommand
{
    public function __construct(MyBot $bot, DependencyContainer $container)
    {
        parent::__construct($bot, $container);
        $this->setDescription($this->translator->translate($this->getDescriptionKey()));
        $this->setAliases($this->translator->translate($this->getAliasesKey()));
    }

    public function getDescriptionKey() : string
    {
        return 'whois_command_description';
    }

    public function getAliasesKey() : string
    {
        return 'whois_command_aliases';
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
        if (!isset($args[0])) {
            $this->bot->sendMessage($chatId, str_replace("{ip_address}", $args[0], $this->translator->translate("whois_usage_message")));

            return;
        }

        $whois = json_decode(file_get_contents("http://ip-api.com/json/" . $args[0]), true);

        if ($whois["status"] !== "success") {
            $this->bot->sendMessage($chatId, $this->translator->translate("whois_no_response_message"));

            return;
        }

        $this->bot->sendMessage($chatId, str_replace(["{ip_address}", "{query}", "{country}", "{country_code}", "{region}", "{city}", "{timezone}", "{lat}", "{lon}", "{org}", "{isp}"], [strtolower($args[0]), $whois["query"], $whois["country"], $whois["countryCode"], $whois["regionName"], $whois["city"], $whois["timezone"], $whois["lat"], $whois["lon"], $whois["org"], $whois["isp"]], $this->translator->translate("whois_info_message")));
    }
}
