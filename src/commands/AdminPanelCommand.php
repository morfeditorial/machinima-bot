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

class AdminPanelCommand extends AbstractCommand
{
    private $dbManager;

    public function __construct(MyBot $bot, DependencyContainer $container)
    {
        parent::__construct($bot, $container);
        $this->setDescription($this->translator->translate($this->getDescriptionKey()));
        $this->setAliases(['admin_panel']);

        $this->dbManager = $container->get('dbManager');
    }

    public function getDescriptionKey() : string
    {
        return 'admin_panel_command_description';
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
        if (! $this->dbManager->hasHigherRole($userId, 'moderator')) {
            $this->bot->sendMessage($chatId, $this->translator->translate('no_permission_message'));

            return;
        }

        $this->dbManager->clearState($userId);
        if (! is_null($currentPanel)) {
            $this->bot->deleteMessage($chatId, $currentPanel);
        }

        $this->dbManager->setCurrentPanel($userId, $messageId + 1);

        if (! is_null($currentPage)) {
            $this->dbManager->resetCurrentPage($userId);
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->translator->translate('add_author'), 'callback_data' => 'add_author'],
                    ['text' => $this->translator->translate('delete_author'), 'callback_data' => 'delete_author'],
                ],
                [
                    ['text' => $this->translator->translate('list_of_authors'), 'callback_data' => 'list_of_authors'],
                ],
                [
                    ['text' => $this->translator->translate('access_control'), 'callback_data' => 'access_control'],
                ],
            ],
        ];

        $this->bot->pictureReply($chatId, $this->translator->translate('admin_panel_message'), $this->visualsLinks[1], $keyboard);
    }
}
