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
use morfeditorial\TelegramBotBundle\Client\TelegramClient;

class DeleteRoleCommand extends BaseMachinimaCommand
{
    public function __construct(TelegramClient $client)
    {
        parent::__construct($client);
        $this->setAliases(['delete_role']);
        $this->setHiddenFromMenu(true);
    }

    public function getCommand() : string
    {
        return 'delete_role';
    }

    public function getDescriptionKey() : string
    {
        return 'delete_role_command_description';
    }

    public function handle(array $update) : void
    {
        $chatId = $update['message']['chat']['id'] ?? 0;
        if (!$chatId) {
            return;
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->client->sendMessage($chatId, $this->translate('no_permission_message'));
            return;
        }

        $args = $this->getArgs($update);
        if (count($args) < 1) {
            $this->client->sendMessage($chatId, $this->translate('delete_role_usage_message'));
            return;
        }

        $role_name = $args[0];

        if (!$this->getRoleService()->getRoleByName($role_name)) {
            $this->client->sendMessage($chatId, str_replace('{roleName}', htmlspecialchars($role_name), $this->translate('role_not_found_message')));
            return;
        }

        if ($this->getRoleService()->deleteRole($role_name)) {
            $this->client->sendMessage($chatId, str_replace(['{role}'], [htmlspecialchars($role_name)], $this->translate('role_deleted_message')));
        } else {
            $this->client->sendMessage($chatId, $this->translate('delete_role_failure_message'));
        }
    }
}
