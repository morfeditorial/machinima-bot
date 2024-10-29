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

require_once __DIR__ . '/vendor/autoload.php';

use morfeditorial\MyBot;
use Dotenv\Dotenv;

// Downloading .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// We get a token from .env
$botToken = getenv('BOT_TOKEN');

if (! $botToken) {
    die('BOT_TOKEN not set in the .env file.');
}

// We initialize the bot with the token
$bot = new MyBot($botToken);

// If you want our bot to work on Webhook, use the code below.
// $update = json_decode(file_get_contents("php://input"), true);
// if ($update) {
//     $bot->handleUpdate($update);
// }

while (true) {
    $bot->getUpdates();
    sleep(1);
}
