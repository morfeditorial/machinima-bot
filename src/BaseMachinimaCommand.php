<?php

declare(strict_types=1);

namespace morfeditorial;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Morfeditorial\TelegramBotBundle\Command\AbstractCommand as BundleAbstractCommand;
use morfeditorial\services\AuthorService;
use morfeditorial\services\RoleService;
use morfeditorial\services\UserService;
use morfeditorial\services\UserStateService;

abstract class BaseMachinimaCommand extends BundleAbstractCommand
{
    protected ContainerInterface $container;
    protected Security $security;
    protected string $description = '';
    protected array $aliases = [];
    protected bool $hidden_from_menu = false;

    /**
     * Using #[Required] allows Symfony to inject these automatically 
     * without forcing us to redefine massive constructors in every child command.
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

    public function translate(string $key)
    {
        return $this->getTranslator()->translate($key);
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
        return $this->security->isGranted($role_name);
    }

    public function getVisualsLinks()
    {
        return $this->container->get('visuals_links');
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }

    public function getAliases(): array
    {
        return $this->aliases;
    }

    abstract public function getDescriptionKey(): string;

    public function setHiddenFromMenu(bool $hidden_from_menu): void
    {
        $this->hidden_from_menu = $hidden_from_menu;
    }

    public function isHiddenFromMenu(): bool
    {
        return $this->hidden_from_menu;
    }

    /**
     * Extracts arguments passed after the command (e.g. /assign_role 123 admin -> ['123', 'admin'])
     */
    protected function getArgs(array $update): array
    {
        $text = $update['message']['text'] ?? '';
        $parts = explode(' ', trim(preg_replace("/\s+/", ' ', $text)));
        return array_slice($parts, 1);
    }
}
