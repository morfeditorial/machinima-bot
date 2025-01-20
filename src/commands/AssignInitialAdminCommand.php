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

class AssignInitialAdminCommand extends AbstractCommand
{
    private $dbManager;

    public function __construct(MyBot $bot, DependencyContainer $container)
    {
        parent::__construct($bot, $container);
        $this->setDescription($this->translator->translate($this->getDescriptionKey()));
        $this->setAliases(['assign_initial_admin']);

        $this->dbManager = $container->get('dbManager');
    }

    public function getDescriptionKey() : string
    {
        return 'assign_initial_admin_command_description';
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
        if (! $this->dbManager->getRoleByName('admin')) {
            $this->dbManager->createRole('admin', 100);
        }

        if (0 < $this->dbManager->getUsersCountByRole('admin')) {
            $this->bot->sendMessage($chatId, $this->translator->translate('already_initialled_admin_message'));

            return;
        }

        $this->dbManager->assignRole($userId, 'admin');

        $this->bot->sendMessage($chatId, $this->translator->translate('success_initialled_admin_message'));
    }
}
