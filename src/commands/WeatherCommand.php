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

class WeatherCommand extends AbstractCommand
{
    public function __construct(MyBot $bot, DependencyContainer $container)
    {
        parent::__construct($bot, $container);
        $this->setDescription($this->translator->translate($this->getDescriptionKey()));
        $this->setAliases(['weather']);
    }

    public function getDescriptionKey() : string
    {
        return 'weather_command_description';
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
        $city = implode(" ", $args);

        if ($city == "") {
            $this->bot->sendMessage($chatId, $this->translator->translate('weather_no_city_specified_message'));
            $this->bot->sendMessage($chatId, $this->translator->translate('weather_usage_message'));

            return;
        }

        if (! isset($_ENV['OPENWEATHERMAP_API_KEY'])) {
            throw new \RuntimeException('OPENWEATHERMAP_API_KEY not set in the .env file.');
        }

        $OWApi_key = $_ENV['OPENWEATHERMAP_API_KEY'];
        $language = $this->translator->getUserLocale() ?: 'en';

        $weather = json_decode(file_get_contents("https://api.openweathermap.org/data/2.5/weather?q={$city}&units=metric&appid={$OWApi_key}&lang={$language}"));

        if (empty($weather)) {
            $this->bot->sendMessage($chatId, $this->translator->translate('weather_fetch_fail_message'));

            return;
        }

        $this->bot->sendMessage($chatId, str_replace(["{city}", "{country}", "{description}", "{wind_speed}", "{cloudiness}", "{pressure}", "{humidity}", "{sunrise}", "{sunset}", "{temp}", "{feels_like}"], [$weather->name, $weather->sys->country, $weather->weather[0]->description, $weather->wind->speed, $weather->clouds->all, intval(($weather->main->pressure) * (0.750063755419211)), $weather->main->humidity, date("H:i:s (e)", $weather->sys->sunrise), date("H:i:s (e)", $weather->sys->sunset), $weather->main->temp, $weather->main->feels_like], $this->translator->translate('weather_report_message')));
    }
}
