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

namespace Morfeditorial\Commands;

use Morfeditorial\BaseMachinimaCommand;
use Morfeditorial\TelegramBotBundle\Client\TelegramClient;

class CreateRoleCommand extends BaseMachinimaCommand
{
    public function __construct(TelegramClient $client)
    {
        parent::__construct($client);
        $this->setAliases(['create_role']);
        $this->setHiddenFromMenu(true);
    }

    public function getCommand() : string
    {
        return 'create_role';
    }

    public function getDescriptionKey() : string
    {
        return 'create_role_command_description';
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
            $this->client->sendMessage($chatId, $this->translate('create_role_usage_message'));
            return;
        }

        $role_name = $args[0];

        if ($this->getRoleService()->getRoleByName($role_name)) {
            $this->client->sendMessage($chatId, str_replace('{roleName}', htmlspecialchars($role_name), $this->translate('role_already_exist_message')));
            return;
        }

        if ($this->getRoleService()->createRole($role_name)) {
            $this->client->sendMessage($chatId, str_replace(['{roleName}'], [htmlspecialchars($role_name)], $this->translate('create_role_message')));
        } else {
            $this->client->sendMessage($chatId, $this->translate('create_role_failure_message'));
        }
    }
}
