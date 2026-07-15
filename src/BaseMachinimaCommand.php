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

namespace Morfeditorial\MachinimaBotBundle;

use Doctrine\ORM\EntityManagerInterface;
use Morfeditorial\MachinimaCoreBundle\Repository\AuthorRepository;
use Morfeditorial\MachinimaCoreBundle\Repository\UserRepository;
use Morfeditorial\MachinimaCoreBundle\Repository\UserStateRepository;
use Morfeditorial\MachinimaCoreBundle\Service\RoleService;
use Morfeditorial\TelegramBotBundle\Command\AbstractCommand as BundleAbstractCommand;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class BaseMachinimaCommand extends BundleAbstractCommand
{
    protected ContainerInterface $container;
    protected Security $security;
    protected EntityManagerInterface $em;
    protected UserRepository $userRepo;
    protected UserStateRepository $userStateRepo;
    protected AuthorRepository $authorRepo;
    protected string $description = '';
    protected array $aliases = [];
    protected bool $hidden_from_menu = false;

    #[Required]
    public function setDependencies(ContainerInterface $container, Security $security, EntityManagerInterface $em, UserRepository $userRepo, UserStateRepository $userStateRepo, AuthorRepository $authorRepo) : void
    {
        $this->container = $container;
        $this->security = $security;
        $this->em = $em;
        $this->userRepo = $userRepo;
        $this->userStateRepo = $userStateRepo;
        $this->authorRepo = $authorRepo;
    }

    public function getTranslator()
    {
        return $this->container->get('bot_translator');
    }

    public function translate(string $key)
    {
        return $this->getTranslator()->translate($key);
    }

    public function getRoleService() : RoleService
    {
        return $this->container->get(RoleService::class);
    }

    public function isGranted(string $role_name) : bool
    {
        return $this->security->isGranted($role_name);
    }

    public function getVisualsLinks()
    {
        return $this->container->get('visuals_links');
    }

    public function setDescription(string $description) : void
    {
        $this->description = $description;
    }

    public function getDescription() : string
    {
        return $this->description;
    }

    public function setAliases(array $aliases) : void
    {
        $this->aliases = $aliases;
    }

    public function getAliases() : array
    {
        return $this->aliases;
    }

    abstract public function getDescriptionKey() : string;

    public function setHiddenFromMenu(bool $hidden_from_menu) : void
    {
        $this->hidden_from_menu = $hidden_from_menu;
    }

    public function isHiddenFromMenu() : bool
    {
        return $this->hidden_from_menu;
    }

    protected function getArgs(array $update) : array
    {
        $text = $update['message']['text'] ?? '';
        $parts = explode(' ', trim(preg_replace("/\s+/", ' ', $text)));
        return array_slice($parts, 1);
    }
}
