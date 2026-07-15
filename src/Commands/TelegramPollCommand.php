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

namespace Morfeditorial\MachinimaBotBundle\Commands;

use Doctrine\ORM\EntityManagerInterface;
use Morfeditorial\TelegramBotBundle\Client\TelegramClient;
use Morfeditorial\TelegramBotBundle\Routing\UpdateDispatcher;
use Morfeditorial\MachinimaBotBundle\Translator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

#[AsCommand(
    name: 'app:telegram:poll',
    description: 'Runs the Telegram bot using long-polling',
)]
class TelegramPollCommand extends Command
{
    private int $offset = 0;

    public function __construct(
        private TelegramClient $telegramClient,
        private UpdateDispatcher $updateDispatcher,
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage,
        private Translator $translator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $io = new SymfonyStyle($input, $output);
        $io->success('Starting Telegram Bot Polling...');

        while (true) {
            try {
                $updates = $this->telegramClient->getUpdates($this->offset);
                foreach ($updates as $update) {
                    $io->writeln('Received update: '.($update['update_id'] ?? 'unknown'));
                    $this->offset = ($update['update_id'] ?? 0) + 1;

                    $userId = $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? null;
                    if ($userId) {
                        // We use the full class name since this bundle relies on the host app's entity
                        $user = $this->entityManager->getRepository(\App\Entity\User::class)->find($userId);
                        if ($user) {
                            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
                            $this->tokenStorage->setToken($token);
                        }

                        $languageCode = $update['message']['from']['language_code'] ?? $update['callback_query']['from']['language_code'] ?? 'en';
                        $this->translator->setUserLocale($languageCode);
                    }

                    $this->updateDispatcher->dispatch($update);
                    $this->tokenStorage->setToken(null);
                    $io->writeln('  -> Dispatched OK');
                }
                sleep(1);
            } catch (\Throwable $e) {
                $io->error($e->getMessage());
                $io->writeln($e->getTraceAsString());
                sleep(5);
            }
        }

        return Command::SUCCESS;
    }
}
