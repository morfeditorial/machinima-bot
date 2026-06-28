<?php

declare(strict_types=1);

namespace morfeditorial\commands;

use morfeditorial\BaseMachinimaCommand;
use Morfeditorial\TelegramBotBundle\Client\TelegramClient;

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
        $this->getUserStateService()->clearState($userId);

        $photoUrl = $this->getVisualsLinks()[0];

        // Використовуємо новий універсальний клієнт замість старого MyBot
        $this->client->sendPhoto($chatId, $photoUrl, [
            'caption' => $this->translate('welcome_message')
        ]);
    }
}
