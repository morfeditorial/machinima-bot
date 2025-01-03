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
use morfeditorial\CommandInterface;
use morfeditorial\DependencyContainer;

class AssignRoleCommand implements CommandInterface
{
    private MyBot $bot;

    private $dbManager;

    private $translator;

    private $visualsLinks;

    public function __construct(MyBot $bot, DependencyContainer $container)
    {
        $this->bot = $bot;
        $this->dbManager = $container->get('dbManager');
        $this->translator = $container->get('translator');
        $this->visualsLinks = $container->get('visualsLinks');
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
        if (! $this->dbManager->hasHigherRole($userId, 'admin')) {
            $this->bot->sendMessage($chatId, $this->translator->translate('no_permission_message'));

            return;
        }

        if (2 > count($args)) {
            $this->bot->sendMessage($chatId, $this->translator->translate('assign_role_usage_message'));

            return;
        }

        $targetUserId = intval($args[0]);
        $roleName = $args[1];

        if (! $this->dbManager->getRoleByName($roleName)) {
            $this->bot->sendMessage($chatId, str_replace('{roleName}', htmlspecialchars($roleName), $this->translator->translate('role_not_found_message')));

            return;
        }

        if ($this->dbManager->assignRole($targetUserId, $roleName)) {
            $this->bot->sendMessage($chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($roleName), $targetUserId], $this->translator->translate('assign_role_message')));
        } else {
            $this->bot->sendMessage($chatId, $this->translator->translate('role_assignment_failure_message'));
        }
    }
}
