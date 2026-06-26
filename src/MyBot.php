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

use morfeditorial\commands\CommandInterface;
use morfeditorial\security\BotUser;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;

class MyBot extends tgLib
{
    private const DATABASE_FILE = __DIR__ . '/../machinimators.db';

    private const TRANSLATIONS_FILE = __DIR__ . '/../translations.json';

    private CommandFactory $command_factory;

    private \morfeditorial\screens\ScreenDispatcher $screen_dispatcher;

    private ContainerInterface $container;

    public function __construct($token)
    {
        parent::__construct($token);

        $translations = json_decode(file_get_contents(self::TRANSLATIONS_FILE), true);

        $container_builder = new ContainerBuilder();

        $container_builder->register('translator', Translator::class)
            ->setArguments([$translations, 'en'])
            ->setPublic(true);

        $container_builder->register('fuzzy_search', FuzzySearch::class)
            ->setAutowired(true)
            ->setPublic(true);

        $container_builder->register('storage', \morfeditorial\storage\DatabaseStorage::class)
            ->setArgument('$connection_params', [
                'driver' => $_ENV['DB_DRIVER'] ?? 'pdo_sqlite',
                'path' => $_ENV['DB_PATH'] ?? self::DATABASE_FILE,
                'host' => $_ENV['DB_HOST'] ?? null,
                'port' => isset($_ENV['DB_PORT']) && '' !== $_ENV['DB_PORT'] ? (int) $_ENV['DB_PORT'] : null,
                'dbname' => $_ENV['DB_NAME'] ?? null,
                'user' => $_ENV['DB_USER'] ?? null,
                'password' => $_ENV['DB_PASSWORD'] ?? null,
            ])
            ->setPublic(true);
        $container_builder->setAlias(\morfeditorial\storage\StorageInterface::class, 'storage');

        $container_builder->register('author_service', \morfeditorial\services\AuthorService::class)
            ->setAutowired(true)
            ->setPublic(true);
        $container_builder->register('user_service', \morfeditorial\services\UserService::class)
            ->setAutowired(true)
            ->setPublic(true);
        $container_builder->register('user_state_service', \morfeditorial\services\UserStateService::class)
            ->setAutowired(true)
            ->setPublic(true);
        $container_builder->register('role_service', \morfeditorial\services\RoleService::class)
            ->setAutowired(true)
            ->setPublic(true);
        $container_builder->setAlias(\morfeditorial\services\RoleService::class, 'role_service');

        $container_builder->register('content_service', \morfeditorial\services\ContentService::class)
            ->setAutowired(true)
            ->setPublic(true);
        $container_builder->setAlias(\morfeditorial\services\ContentService::class, 'content_service');

        $container_builder->register('content_workflow_definition', \Symfony\Component\Workflow\Definition::class)
            ->setFactory([self::class, 'createContentWorkflowDefinition']);

        $container_builder->register('content_workflow', \Symfony\Component\Workflow\Workflow::class)
            ->setArguments([
                new Reference('content_workflow_definition'),
                new \Symfony\Component\Workflow\MarkingStore\MethodMarkingStore(true, 'status'),
            ])
            ->setPublic(true);
        $container_builder->setAlias(\Symfony\Component\Workflow\Workflow::class, 'content_workflow');

        $container_builder->register('token_storage', \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage::class)
            ->setPublic(true);
        $container_builder->register('role_hierarchy_voter', \morfeditorial\security\RoleHierarchyVoter::class)
            ->setAutowired(true)
            ->setPublic(true);
        $container_builder->register('access_decision_manager', \Symfony\Component\Security\Core\Authorization\AccessDecisionManager::class)
            ->setArgument('$voters', [new Reference('role_hierarchy_voter')])
            ->setPublic(true);
        $container_builder->register('authorization_checker', \Symfony\Component\Security\Core\Authorization\AuthorizationChecker::class)
            ->setArguments([
                new Reference('token_storage'),
                new Reference('access_decision_manager'),
            ])
            ->setPublic(true);

        $container_builder->register('visuals_links', \ArrayObject::class)
            ->setArguments([[
                'https://i.ibb.co/mC7sv0W/01.png', // WELCOME_TO_MORF
                'https://i.ibb.co/ygqgFMV/02.png', // WELCOME_ADMIN_PANEL
                'https://i.ibb.co/1KysC55/03.png', // NEW_MACHINIMATOR_ADDED
                'https://i.ibb.co/64vFVfS/04.png', // AUTHOR_NAME_CHANGE
                'https://i.ibb.co/TLDzmVf/05.png', // ADD_MACHINIMATOR_BIO
                'https://i.ibb.co/85LB6PW/06.png', // EDIT_MACHINIMATOR_BIO
                'https://i.ibb.co/fDbmdMM/07.png', // SUCCESSFUL_NAME_CHANGE
                'https://i.ibb.co/tqMNGtX/08.png', // BIOGRAPHY_ADDED
                'https://i.ibb.co/xF1QQXk/09.png', // BIOGRAPHY_EDITED
                'https://i.ibb.co/LPYBSBX/10.png', // LIST_ALL_MACHINIMATORS
                'https://i.ibb.co/Fn2HJJQ/11.png', // CREATE_NEW_MACHINIMATOR
                'https://i.ibb.co/TYPWsLQ/12.png', // AUTHOR_INFO_MANAGEMENT
            ]])
            ->setPublic(true);

        $container_builder->compile();

        $this->container = $container_builder;

        $this->command_factory = new CommandFactory($this, $this->container);
        $this->initializeCommands();

        $this->screen_dispatcher = new \morfeditorial\screens\ScreenDispatcher($this);
    }

    private function initializeCommands() : void
    {
        // Register commands
        $this->command_factory->registerCommand(new \morfeditorial\commands\StartCommand($this)); // start
        $this->command_factory->registerCommand(new \morfeditorial\commands\HelpCommand($this)); // help
        $this->command_factory->registerCommand(new \morfeditorial\commands\UpdateCommand($this)); // update
        $this->command_factory->registerCommand(new \morfeditorial\commands\AdminPanelCommand($this)); // admin_panel
        // $this->command_factory->registerCommand(new \morfeditorial\commands\SearchContentCommand($this)); // search_content
        $this->command_factory->registerCommand(new \morfeditorial\commands\SearchAuthorCommand($this)); // search_author
        // $this->command_factory->registerCommand(new \morfeditorial\commands\CategoriesCommand($this)); // categories
        // $this->command_factory->registerCommand(new \morfeditorial\commands\TopAuthorsCommand($this)); // top_authors
        // $this->command_factory->registerCommand(new \morfeditorial\commands\RandomContentCommand($this)); // random_content
        $this->command_factory->registerCommand(new \morfeditorial\commands\CreateRoleCommand($this)); // create_role
        $this->command_factory->registerCommand(new \morfeditorial\commands\DeleteRoleCommand($this)); // delete_role
        $this->command_factory->registerCommand(new \morfeditorial\commands\AssignInitialAdminCommand($this)); // assign_initial_admin
        $this->command_factory->registerCommand(new \morfeditorial\commands\AssignRoleCommand($this)); // assign_role

        $this->command_factory->initializeCommands();
    }

    public function handleUpdate($update) : void
    {
        $message_data = $this->extractMessageData($update);
        $message = $message_data['message'] ?? null;
        $photo = $message_data['photo'] ?? null;

        if (null !== $message_data['user_id']) {
            $this->setupUserToken($message_data['user_id']);
        }

        $this->container->get('translator')->setUserLocale($message_data['language_code'] ?? 'en');

        if (null !== $message || null !== $photo) {
            $this->processMessage($message_data, $message);
        } else {
            $payload = $message_data['payload'] ?? '';

            if (str_contains($payload, ':')) {
                $this->screen_dispatcher->dispatchCallback($message_data, $payload);
            }
        }
    }

    public function getContainer() : \Symfony\Component\DependencyInjection\ContainerInterface
    {
        return $this->container;
    }

    public function getUserService() : \morfeditorial\services\UserService
    {
        return $this->container->get('user_service');
    }

    public function getUserStateService() : \morfeditorial\services\UserStateService
    {
        return $this->container->get('user_state_service');
    }

    private function processMessage(array $message_data, ?string $message) : void
    {
        $command_data = null !== $message ? $this->parseCommand($message) : null;
        if ($command_data) {
            $this->executeCommand($command_data, $message_data);
        } else {
            $text = $message_data['message'] ?? '';
            $this->screen_dispatcher->dispatchMessage($message_data, $text);
        }
    }

    private function parseCommand(string $message) : ?array
    {
        $parts = explode(' ', trim(preg_replace("/\s+/", ' ', $message)));
        if (! empty($parts) && in_array($parts[0][0], ['/', '!'])) {
            $cmd = ltrim(mb_strtolower($parts[0], 'utf-8'), '/!');
            $args = array_slice($parts, 1);

            return ['cmd' => $cmd, 'args' => $args];
        }

        return null;
    }

    private function executeCommand(array $command_data, array $message_data) : void
    {
        $command = $this->command_factory->create($command_data['cmd']);
        if ($command instanceof CommandInterface) {
            $command->execute(
                $message_data['message'],
                $message_data['message_id'],
                $message_data['chat_type'],
                $message_data['chat_id'],
                $message_data['user_id'],
                $message_data['payload'],
                $message_data['reply_message_id'],
                $message_data['reply_author'],
                $message_data['first_name'],
                $this->container->get('user_service')->getCurrentPanel($message_data['user_id']),
                $this->container->get('user_service')->getCurrentPage($message_data['user_id']),
                $command_data['cmd'],
                $command_data['args']
            );
        } else {
            $this->sendMessage($message_data['chat_id'], $this->translate('unknown_command_message'));
        }
    }

    private function extractMessageData($data)
    {
        $callback_query = $data['callback_query'] ?? null;

        return [
            'message' => $callback_query['message']['text'] ?? $data['message']['text'] ?? null,
            'message_id' => $callback_query['message']['message_id'] ?? $data['message']['message_id'] ?? null,
            'chat_type' => $callback_query['message']['chat']['type'] ?? $data['message']['chat']['type'] ?? null,
            'chat_id' => $callback_query['message']['chat']['id'] ?? $data['message']['chat']['id'] ?? null,
            'user_id' => $callback_query['from']['id'] ?? $data['message']['from']['id'] ?? null,
            'language_code' => $callback_query['from']['language_code'] ?? $data['message']['from']['language_code'] ?? null,
            'payload' => $callback_query['data'] ?? null,
            'callback_query_id' => $callback_query['id'] ?? null,
            'reply_message_id' => $data['message']['reply_to_message']['message_id'] ?? null,
            'reply_author' => $data['message']['reply_to_message']['from']['id'] ?? null,
            'first_name' => $data['message']['from']['first_name'] ?? null,
            'photo' => $data['message']['photo'] ?? null,
        ];
    }

    private function setupUserToken(int $user_id) : void
    {
        $role_names = $this->container->get('role_service')->getUserRoleNames($user_id);
        $bot_user = new BotUser($user_id, $role_names);
        $token = new PreAuthenticatedToken($bot_user, 'main', $role_names);
        $this->container->get('token_storage')->setToken($token);
    }

    public function isGranted(string $role) : bool
    {
        return $this->container->get('authorization_checker')->isGranted($role);
    }

    public static function createContentWorkflowDefinition() : \Symfony\Component\Workflow\Definition
    {
        $builder = new \Symfony\Component\Workflow\DefinitionBuilder();
        $builder->addPlaces(['draft', 'pending_review', 'published', 'rejected']);

        $builder->addTransition(new \Symfony\Component\Workflow\Transition('submit', 'draft', 'pending_review'));
        $builder->addTransition(new \Symfony\Component\Workflow\Transition('publish', 'pending_review', 'published'));
        $builder->addTransition(new \Symfony\Component\Workflow\Transition('reject', 'pending_review', 'rejected'));
        $builder->addTransition(new \Symfony\Component\Workflow\Transition('re-draft', 'rejected', 'draft'));

        return $builder->build();
    }

    protected function createAvatar($author_name, $author_link, $author_image = 'path/to/author_image.jpg')
    {
        $template = imagecreatefrompng('path/to/template.png');
        $author_image = imagecreatefrompng($author_image);
        $stamp = imagecreatefrompng('path/to/stamp.png');

        $author_width = imagesx($author_image);
        $author_height = imagesy($author_image);

        $crop_width = min($author_width, $author_height);
        $crop_height = min($author_width, $author_height);

        $cropped_author_image = imagecreatetruecolor($crop_width, $crop_height);
        imagecopyresized($cropped_author_image, $author_image, 0, 0, ($author_width - $crop_width) / 2, ($author_height - $crop_height) / 2, $crop_width, $crop_height, $crop_width, $crop_height);
        imagecopyresampled($template, $cropped_author_image, 53, 80, 0, 0, 240, 260, $crop_width, $crop_height);

        imagecopy($template, $stamp, 175, 125, 0, 0, 300, 300);

        $text_color = imagecolorallocate($template, 0, 0, 0);

        $font = 'path/to/font.ttf';

        $text_width = imagettfbbox(20, -1, $font, $author_name);
        $text_width = $text_width[2] - $text_width[0];

        $x = (imagesx($template) - $text_width) / 2;
        $y = 440;

        imagettftext($template, 20, -1, $x, $y, $text_color, $font, $author_name);

        $text_width = imagettfbbox(20, -1, $font, $author_link);
        $text_width = $text_width[2] - $text_width[0];

        $x = (imagesx($template) - $text_width) / 2;
        $y = 575;

        imagettftext($template, 20, -1, $x, $y, $text_color, $font, $author_link);

        $temp_file = tempnam(sys_get_temp_dir(), 'img');
        imagepng($template, $temp_file);

        return curl_file_create($temp_file);
    }

    public function generateAuthorsKeyboard(
        int $page_number = 1,
        int $buttons_per_page = 3,
        int $authors_per_row = 1,
        string $prefix = 'author:profile:',
        string $page_prefix = 'author:list:',
        ?array $authors = null
    ) : array {
        $is_from_database = is_null($authors);
        $authors = $authors ?? $this->container->get('author_service')->getAllAuthors();
        $total_buttons = count($authors);
        $total_pages = (int) ceil($total_buttons / $buttons_per_page);
        $page_number = max(1, min($page_number, $total_pages));

        $sliced_authors = array_slice($authors, ($page_number - 1) * $buttons_per_page, $buttons_per_page);
        $keyboard = ['inline_keyboard' => []];

        $current_row = [];
        foreach ($sliced_authors as $author) {
            $current_row[] = ['text' => $author['name'], 'callback_data' => $prefix . $author['id']];
            if (count($current_row) === $authors_per_row) {
                $keyboard['inline_keyboard'][] = $current_row;
                $current_row = [];
            }
        }
        if (! empty($current_row)) {
            $keyboard['inline_keyboard'][] = $current_row;
        }

        if ($total_pages > 1) {
            $pagination = [];
            if ($page_number > 1) {
                $pagination[] = ['text' => $this->translate('previous_page'), 'callback_data' => $page_prefix . ($page_number - 1)];
            }
            if ($page_number < $total_pages) {
                $pagination[] = ['text' => $this->translate('next_page'), 'callback_data' => $page_prefix . ($page_number + 1)];
            }
            $keyboard['inline_keyboard'][] = $pagination;
        }

        if ($is_from_database) {
            $keyboard['inline_keyboard'][] = [['text' => $this->translate('go_back'), 'callback_data' => 'admin:panel']];
        }

        return $keyboard;
    }

    private function translate(string $key)
    {
        return $this->container->get('translator')->translate($key);
    }
}
