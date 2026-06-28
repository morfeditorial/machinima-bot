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

namespace morfeditorial;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\Attribute\Required;
use TelegramBot\Bundle\Screen\AbstractScreen as BundleAbstractScreen;
use morfeditorial\services\AuthorService;
use morfeditorial\services\RoleService;
use morfeditorial\services\UserService;
use morfeditorial\services\UserStateService;

abstract class BaseMachinimaScreen extends BundleAbstractScreen
{
    protected ContainerInterface $container;
    protected Security $security;

    /**
     * Using #[Required] allows Symfony to inject these automatically 
     * without forcing us to redefine constructors in every child screen.
     */
    #[Required]
    public function setDependencies(ContainerInterface $container, Security $security): void
    {
        $this->container = $container;
        $this->security = $security;
    }

    public function getTranslator()
    {
        return $this->container->get('bot_translator');
    }

    public function translate(string $key, array $params = []): string
    {
        return $this->getTranslator()->translate($key, $params);
    }

    public function getAuthorService(): AuthorService
    {
        return $this->container->get('author_service');
    }

    public function getUserService(): UserService
    {
        return $this->container->get('user_service');
    }

    public function getUserStateService(): UserStateService
    {
        return $this->container->get('user_state_service');
    }

    public function getRoleService(): RoleService
    {
        return $this->container->get('role_service');
    }

    public function isGranted(string $role_name): bool
    {
        $symfony_role = 'ROLE_' . strtoupper($role_name);
        return $this->security->isGranted($symfony_role);
    }

    public function getVisualsLinks()
    {
        return $this->container->get('visuals_links');
    }

    /**
     * Parse action and parameters from payload domain:action:arg1:arg2
     * Returns ['domain' => 'domain', 'action' => 'action', 'params' => ['arg1', 'arg2']]
     */
    protected function parsePayload(string $payload): array
    {
        $parts = explode(':', $payload);
        $domain = array_shift($parts) ?? '';
        $action = array_shift($parts) ?? '';

        return [
            'domain' => $domain,
            'action' => $action,
            'params' => $parts
        ];
    }

    /**
     * Create formatted payload for buttons
     */
    protected function makePayload(string $domain, string $action, ...$params): string
    {
        $parts = [$domain, $action, ...$params];
        return implode(':', $parts);
    }
}
