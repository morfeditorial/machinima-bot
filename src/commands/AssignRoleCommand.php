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

namespace Morfeditorial\commands;

use Morfeditorial\BaseMachinimaCommand;
use Morfeditorial\TelegramBotBundle\Client\TelegramClient;

class AssignRoleCommand extends BaseMachinimaCommand
{
    public function __construct(TelegramClient $client)
    {
        parent::__construct($client);
        $this->setAliases(['assign_role']);
        $this->setHiddenFromMenu(true);
    }

    public function getCommand() : string
    {
        return 'assign_role';
    }

    public function getDescriptionKey() : string
    {
        return 'assign_role_command_description';
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
        if (count($args) < 2) {
            $this->client->sendMessage($chatId, $this->translate('assign_role_usage_message'));
            return;
        }

        $target_user_id = intval($args[0]);
        $role_name = $args[1];

        if (!$this->getRoleService()->getRoleByName($role_name)) {
            $this->client->sendMessage($chatId, str_replace('{roleName}', htmlspecialchars($role_name), $this->translate('role_not_found_message')));
            return;
        }

        $result = $this->getRoleService()->assignRole($target_user_id, $role_name);

        if ('success' === $result) {
            $this->client->sendMessage($chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('assign_role_message')));
        } elseif ('already_assigned' === $result) {
            $this->client->sendMessage($chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('role_already_assigned_message')));
        } else {
            $this->client->sendMessage($chatId, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('role_assignment_failure_message')));
        }
    }
}
