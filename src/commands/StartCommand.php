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

use morfeditorial\BaseMachinimaCommand;
use Morfeditorial\TelegramBotBundle\Client\TelegramClient;
use App\Entity\User;
use App\Entity\UserState;

class StartCommand extends BaseMachinimaCommand
{
    public function __construct(TelegramClient $client)
    {
        parent::__construct($client);
        $this->setAliases(['start', 'begin', 'initiate']);
    }

    public function getCommand(): string
    {
        return 'start'; // Диспетчер шукатиме саме цю команду
    }

    public function getDescriptionKey(): string
    {
        return 'start_command_description';
    }

    public function handle(array $update): void
    {
        $chatId = $update['message']['chat']['id'] ?? 0;
        $userId = $update['message']['from']['id'] ?? 0;

        if (!$chatId || !$userId) {
            return;
        }

        // Викликаємо стару звичну бізнес-логіку
        $user = $this->em->find(User::class, $userId);
        if ($user) {
            $states = $this->em->getRepository(UserState::class)->findBy(['user' => $user]);
            foreach ($states as $state) {
                $this->em->remove($state);
            }
            $this->em->flush();
        }

        $photoUrl = $this->getVisualsLinks()[0];

        // Використовуємо новий універсальний клієнт замість старого MyBot
        $this->client->sendPhoto($chatId, $photoUrl, [
            'caption' => $this->translate('welcome_message')
        ]);
    }
}
