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

        if (null !== $message_data['user_id']) {
            $this->setupUserToken($message_data['user_id']);
        }

        $this->container->get('translator')->setUserLocale($message_data['language_code'] ?? 'en');

        if ($message) {
            $this->processMessage($message_data, $message);
        } else {
            $this->handlePanels($message_data);
        }
    }

    private function processMessage(array $message_data, string $message) : void
    {
        $command_data = $this->parseCommand($message);
        if ($command_data) {
            $this->executeCommand($command_data, $message_data);
        } else {
            $this->handleStates($message_data);
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

    private function handleStates($message_data)
    {
        $message = $message_data['message'];
        $message_id = $message_data['message_id'];
        $chat_type = $message_data['chat_type'];
        $chat_id = $message_data['chat_id'];
        $user_id = $message_data['user_id'] !== $chat_id ? $chat_id : $message_data['user_id'];
        $payload = $message_data['payload'];
        $first_name = $message_data['first_name'];
        $user_service = $this->container->get('user_service');
        $user_state_service = $this->container->get('user_state_service');
        $role_service = $this->container->get('role_service');
        $author_service = $this->container->get('author_service');
        $visuals_links = $this->container->get('visuals_links');
        $current_panel = $user_service->getCurrentPanel($user_id);
        $current_page = $user_service->getCurrentPage($user_id);
        $default_state = $user_state_service->getState($user_id);

        if ('awaiting_author_name_creation' === $default_state) {
            if (! $this->isGranted('moderator')) {
                $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                return;
            }
            $this->deleteMessage($chat_id, $message_id);
            $user_state_service->clearState($user_id, 'default');
            $author_id = $author_service->createAuthor($message);
            $author_status = $author_service->isPrivate($author_id);
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('change_name'), 'callback_data' => 'change_name_' . $author_id],
                        ['text' => ($author_status ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'set_private_' . $author_id],
                    ],
                    [
                        ['text' => $this->translate('add_bio'), 'callback_data' => 'set_author_about_' . $author_id],
                        ['text' => $this->translate('add_link'), 'callback_data' => 'add_author_link_' . $author_id],
                    ],
                    [
                        ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author_to_delete_' . $author_id],
                    ],
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => 'control_panel'],
                    ],
                ],
            ];
            $this->editMediaMessage($chat_id, $current_panel, $visuals_links[2], str_replace('{author}', htmlspecialchars($message), $this->translate('author_added_message')), $keyboard);
        } elseif ($state = $user_state_service->getState($user_id, 'change_name')) {
            if (! $this->isGranted('moderator')) {
                $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                return;
            }
            $this->deleteMessage($chat_id, $message_id);
            $user_state_service->clearState($user_id, 'change_name');
            $author_id = $state['author_id'];
            $author = $author_service->getAuthorById($author_id);
            $author_service->updateAuthorName($author_id, $message);
            $author_status = $author_service->isPrivate($author_id);
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('change_name'), 'callback_data' => 'change_name_' . $author_id],
                        ['text' => ($author_status ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'set_private_' . $author_id],
                    ],
                    [
                        ['text' => $this->translate('change_bio'), 'callback_data' => 'set_author_about_' . $author_id],
                        ['text' => ($author['channel_link'] ? $this->translate('change_link') : $this->translate('add_link')), 'callback_data' => 'add_author_link_' . $author_id],
                    ],
                    [
                        ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author_to_delete_' . $author_id],
                    ],
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $current_page ?? 'control_panel'],
                    ],
                ],
            ];
            $this->editMediaMessage($chat_id, $current_panel, $visuals_links[6], str_replace(['{author}', '{oldName}', '{biography}', '{link}'], [htmlspecialchars($message), htmlspecialchars($author['name']), ($author['biography'] ? htmlspecialchars($author['biography']) : $this->translate('bio_not_set')), ($author['channel_link'] ? htmlspecialchars($author['channel_link']) : $this->translate('link_not_set'))], $this->translate('name_changed_message')), $keyboard);
        } elseif ($state = $user_state_service->getState($user_id, 'set_author_about')) {
            if (! $this->isGranted('moderator')) {
                $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                return;
            }
            $this->deleteMessage($chat_id, $message_id);
            $user_state_service->clearState($user_id, 'set_author_about');
            $author_id = $state['author_id'];
            $author = $author_service->getAuthorById($author_id);
            $author_service->setBiography($author_id, $message);
            $author_status = $author_service->isPrivate($author_id);
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('change_name'), 'callback_data' => 'change_name_' . $author_id],
                        ['text' => ($author_status ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'set_private_' . $author_id],
                    ],
                    [
                        ['text' => $this->translate('change_bio'), 'callback_data' => 'set_author_about_' . $author_id],
                        ['text' => ($author['channel_link'] ? $this->translate('change_link') : $this->translate('add_link')), 'callback_data' => 'add_author_link_' . $author_id],
                    ],
                    [
                        ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author_to_delete_' . $author_id],
                    ],
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $current_page ?? 'control_panel'],
                    ],
                ],
            ];
            $this->editMediaMessage($chat_id, $current_panel, ($author['biography'] ? $visuals_links[8] : $visuals_links[7]), str_replace(['{author}', '{biography}', '{link}'], [htmlspecialchars($author['name']), htmlspecialchars($message), ($author['channel_link'] ? htmlspecialchars($author['channel_link']) : $this->translate('link_not_set'))], ($author['biography'] ? $this->translate('bio_changed_message') : $this->translate('bio_added_message'))), $keyboard);
        } elseif ($state = $user_state_service->getState($user_id, 'add_author_link')) {
            if (! $this->isGranted('moderator')) {
                $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                return;
            }
            $this->deleteMessage($chat_id, $message_id);
            $user_state_service->clearState($user_id, 'add_author_link');
            $author_id = $state['author_id'];
            $author = $author_service->getAuthorById($author_id);
            $author_service->setChannelLink($author_id, $message);
            $author_status = $author_service->isPrivate($author_id);
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => $this->translate('change_name'), 'callback_data' => 'change_name_' . $author_id],
                        ['text' => ($author_status ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'set_private_' . $author_id],
                    ],
                    [
                        ['text' => ($author['biography'] ? $this->translate('change_bio') : $this->translate('add_bio')), 'callback_data' => 'set_author_about_' . $author_id],
                        ['text' => $this->translate('change_link'), 'callback_data' => 'add_author_link_' . $author_id],
                    ],
                    [
                        ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author_to_delete_' . $author_id],
                    ],
                    [
                        ['text' => $this->translate('go_back'), 'callback_data' => $current_page ?? 'control_panel'],
                    ],
                ],
            ];
            $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], str_replace(['{author}', '{biography}', '{link}'], [htmlspecialchars($author['name']), ($author['biography'] ? htmlspecialchars($author['biography']) : $this->translate('bio_not_set')), htmlspecialchars($message)], $this->translate('link_changed_message')), $keyboard);
        } elseif ('awaiting_role_creation' === $default_state) {
            if ($role_service->getRoleByName($message)) {
                $this->sendMessage($chat_id, $this->translate('role_already_exist_text_message'));

                return;
            }

            $role_service->createRole($message);
            $user_state_service->clearState($user_id, 'default');

            $all_roles = $role_service->getAllRolesSorted();
            $keyboard = ['inline_keyboard' => []];

            foreach ($all_roles as $role) {
                if ($role['role_name'] === $message) {
                    continue;
                }
                $keyboard['inline_keyboard'][] = [
                    ['text' => $role['role_name'], 'callback_data' => 'confirm_parent:' . $role['role_name'] . ':' . $message],
                ];
            }

            $keyboard['inline_keyboard'][] = [
                ['text' => $this->translate('no_parent'), 'callback_data' => 'view_roles'],
            ];

            $this->sendMessage($chat_id, str_replace('{role}', $message, $this->translate('role_created_redirect_message')));
            $this->editMediaMessage($chat_id, $user_service->getCurrentPanel($user_id), $visuals_links[1], str_replace('{role}', $message, $this->translate('select_parent_message')), $keyboard);
        } elseif ($state_data = $user_state_service->getState($user_id, 'awaiting_user_id_for_role')) {
            $target_user_id = (int) $message;

            if ($target_user_id <= 0) {
                $this->sendMessage($chat_id, $this->translate('invalid_user_id_message'));

                return;
            }

            $role_name = $state_data['role_name'] ?? '';

            if ($role_service->assignRole($target_user_id, $role_name)) {
                $this->sendMessage($chat_id, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('assign_role_message')));
            } else {
                $this->sendMessage($chat_id, str_replace(['{roleName}', '{userId}'], [htmlspecialchars($role_name), $target_user_id], $this->translate('role_assignment_failure_message')));
            }

            $user_state_service->clearState($user_id, 'awaiting_user_id_for_role');
        }
    }

    private function handlePanels($message_data)
    {
        $message = $message_data['message'];
        $message_id = $message_data['message_id'];
        $chat_type = $message_data['chat_type'];
        $chat_id = $message_data['chat_id'];
        $user_id = $message_data['user_id'] !== $chat_id ? $chat_id : $message_data['user_id'];
        $payload = $message_data['payload'];
        $callback_query_id = $message_data['callback_query_id'];
        $first_name = $message_data['first_name'];
        $user_service = $this->container->get('user_service');
        $user_state_service = $this->container->get('user_state_service');
        $author_service = $this->container->get('author_service');
        $visuals_links = $this->container->get('visuals_links');

        if (null !== $payload) {
            $current_panel = $user_service->getCurrentPanel($user_id);
            $current_page = $user_service->getCurrentPage($user_id);

            if ('control_panel' === $payload) {
                if (! $this->isGranted('moderator')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }
                $user_state_service->clearState($user_id);
                if (! is_null($current_page)) {
                    $user_service->resetCurrentPage($user_id);
                }
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('add_author'), 'callback_data' => 'add_author'],
                            ['text' => $this->translate('delete_author'), 'callback_data' => 'delete_author'],
                        ],
                        [
                            ['text' => $this->translate('list_of_authors'), 'callback_data' => 'list_of_authors'],
                        ],
                        [
                            ['text' => $this->translate('access_control'), 'callback_data' => 'access_control'],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $this->translate('admin_panel_message'), $keyboard);
            } elseif ('add_author' === $payload) {
                if (! $this->isGranted('moderator')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }
                $user_state_service->setState($user_id, 'awaiting_author_name_creation');
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => 'control_panel'],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[10], $this->translate('add_author_message'), $keyboard);
            } elseif ('delete_author' === $payload) {
                if (! $this->isGranted('moderator')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }
                $user_service->setCurrentPage($user_id, 'delete_page_1');
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $this->translate('delete_author_message'), $this->generateAuthorsKeyboard(1, 3, 1, 'author_to_delete_', 'delete_page_'));
            } elseif (preg_match("/^delete_page_(\d+)$/", $payload, $matches)) {
                if (! $this->isGranted('moderator')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }
                $user_service->setCurrentPage($user_id, $payload);
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $this->translate('delete_author_message'), $this->generateAuthorsKeyboard((int) $matches[1], 3, 1, 'author_to_delete_', 'delete_page_'));
            } elseif (preg_match("/^author_to_delete_(\d+)$/", $payload, $matches)) {
                if (! $this->isGranted('moderator')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }
                preg_match("/^delete_page_(\d+)$/", $current_page ?? 'page_', $prefix);
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('confirm_delete'), 'callback_data' => 'delete_confirmation_' . $matches[1]],
                        ],
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => $prefix[0] ?? 'author_' . $matches[1]],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], str_replace('{author}', htmlspecialchars($author_service->getAuthorById(intval($matches[1]))['name']), $this->translate('confirm_delete_message')), $keyboard);
            } elseif (preg_match("/^delete_confirmation_(\d+)$/", $payload, $matches)) {
                if (! $this->isGranted('moderator')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }
                $author = $author_service->getAuthorById(intval($matches[1]));
                $this->deleteAuthor($matches[1]);
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => $current_page ?? 'control_panel'],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], str_replace('{author}', htmlspecialchars($author['name']), $this->translate('author_deleted_message')), $keyboard);
            } elseif (preg_match("/^change_name_(\d+)$/", $payload, $matches)) {
                if (! $this->isGranted('moderator')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }
                $user_state_service->setState($user_id, ['author_id' => intval($matches[1])], 'change_name');
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => 'author_' . $matches[1]],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[3], $this->translate('pending_name_change'), $keyboard);
            } elseif (preg_match("/^set_author_about_(\d+)$/", $payload, $matches)) {
                if (! $this->isGranted('moderator')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }
                $author = $author_service->getAuthorById(intval($matches[1]));
                $user_state_service->setState($user_id, ['author_id' => intval($matches[1])], 'set_author_about');
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => 'author_' . $matches[1]],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, ($author['biography'] ? $visuals_links[5] : $visuals_links[4]), ($author['biography'] ? $this->translate('pending_bio_change') : $this->translate('pending_bio_add')), $keyboard);
            } elseif (preg_match("/^add_author_link_(\d+)$/", $payload, $matches)) {
                if (! $this->isGranted('moderator')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }
                $user_state_service->setState($user_id, ['author_id' => intval($matches[1])], 'add_author_link');
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => 'author_' . $matches[1]],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $this->translate('pending_link_change'), $keyboard);
            } elseif ('list_of_authors' === $payload) {
                if (! $this->isGranted('moderator')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }
                $user_service->setCurrentPage($user_id, 'page_1');
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[9], $this->translate('list_of_authors_message'), $this->generateAuthorsKeyboard());
            } elseif (preg_match("/^page_(\d+)$/", $payload, $matches)) {
                if (! $this->isGranted('moderator')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }
                $user_service->setCurrentPage($user_id, $payload);
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[9], $this->translate('list_of_authors_message'), $this->generateAuthorsKeyboard((int) $matches[1]));
            } elseif (preg_match("/^author_(\d+)$/", $payload, $matches)) {
                if (! $this->isGranted('moderator')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }
                $author = $author_service->getAuthorById(intval($matches[1]));
                if (false !== $author) {
                    $author_status = $author_service->isPrivate(intval($matches[1]));
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $this->translate('change_name'), 'callback_data' => 'change_name_' . $matches[1]],
                                ['text' => ($author_status ? $this->translate('make_public') : $this->translate('make_private')), 'callback_data' => 'set_private_' . $matches[1]],
                            ],
                            [
                                ['text' => ($author['biography'] ? $this->translate('change_bio') : $this->translate('add_bio')), 'callback_data' => 'set_author_about_' . $matches[1]],
                                ['text' => ($author['channel_link'] ? $this->translate('change_link') : $this->translate('add_link')), 'callback_data' => 'add_author_link_' . $matches[1]],
                            ],
                            [
                                ['text' => $this->translate('delete_this_author'), 'callback_data' => 'author_to_delete_' . $matches[1]],
                            ],
                            [
                                ['text' => $this->translate('go_back'), 'callback_data' => $current_page ?? 'control_panel'],
                            ],
                        ],
                    ];
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[11], str_replace(['{author}', '{biography}', '{link}'], [htmlspecialchars($author['name']), ($author['biography'] ? htmlspecialchars($author['biography']) : $this->translate('bio_not_set')), ($author['channel_link'] ? htmlspecialchars($author['channel_link']) : $this->translate('link_not_set'))], $this->translate('author_info_message')), $keyboard);

                    return;
                }
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => 'control_panel'],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $this->translate('author_not_found_message'), $keyboard);
            } elseif (preg_match("/^profile_author_(\d+)$/", $payload, $matches)) {
                $author = $author_service->getAuthorById(intval($matches[1]));
                if (false !== $author) {
                    $avatar = $this->createAvatar($author['name'], $author['channel_link']);
                    $this->pictureReply($chat_id, str_replace(['{author}', '{biography}', '{link}'], [htmlspecialchars($author['name']), ($author['biography'] ? htmlspecialchars($author['biography']) : $this->translate('bio_not_set')), ($author['channel_link'] ? htmlspecialchars($author['channel_link']) : $this->translate('link_not_set'))], $this->translate('author_info_message')), $avatar);
                    unlink($avatar);

                    return;
                }
                $this->sendMessage($chat_id, $this->translate('author_not_found_message'));
            } elseif ('access_control' === $payload) {
                if (! $this->isGranted('admin')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }
                $user_state_service->clearState($user_id);
                if (! is_null($current_page)) {
                    $user_service->resetCurrentPage($user_id);
                }
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('create_role'), 'callback_data' => 'create_role'],
                            ['text' => $this->translate('delete_role'), 'callback_data' => 'delete_role'],
                        ],
                        [
                            ['text' => $this->translate('view_roles'), 'callback_data' => 'view_roles'],
                        ],
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => 'control_panel'],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $this->translate('access_control_panel_message'), $keyboard);
            } elseif ('view_roles' === $payload) {
                if (! $this->isGranted('admin')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }

                $role_service = $this->container->get('role_service');
                $all_roles = $role_service->getAllRolesSorted();
                $hierarchy = $role_service->getRoleHierarchy();

                $keyboard = ['inline_keyboard' => []];

                foreach ($all_roles as $role) {
                    $raw_children = $hierarchy['ROLE_' . $role['role_name']] ?? [];
                    $children_names = array_map(fn ($r) => str_replace('ROLE_', '', $r), $raw_children);
                    $children_text = ! empty($children_names) ? implode(', ', $children_names) : "\u{2014}";

                    $keyboard['inline_keyboard'][] = [
                        [
                            'text' => $role['role_name'],
                            'callback_data' => 'show_role:' . $role['role_name'],
                        ],
                        [
                            'text' => $children_text,
                            'callback_data' => 'show_role:' . $role['role_name'],
                        ],
                    ];
                }

                $keyboard['inline_keyboard'][] = [
                    ['text' => $this->translate('go_back'), 'callback_data' => 'access_control'],
                ];

                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $this->translate('role_hierarchy_message'), $keyboard);
            } elseif (preg_match("/^show_role:(.+)$/", $payload, $matches)) {
                if (! $this->isGranted('admin')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }

                $role_service = $this->container->get('role_service');
                $role_name = $matches[1];
                $role = $role_service->getRoleByName($role_name);

                if (! $role) {
                    $this->sendMessage($chat_id, str_replace('{roleName}', htmlspecialchars($role_name), $this->translate('role_not_found_message')));

                    return;
                }

                $parents = $role_service->getParents($role_name);
                $children = $role_service->getChildren($role_name);
                $parents_text = ! empty($parents) ? implode(', ', array_column($parents, 'role_name')) : "\u{2014}";
                $children_text = ! empty($children) ? implode(', ', array_column($children, 'role_name')) : "\u{2014}";

                $message_text = str_replace(
                    ['{role}', '{parents}', '{children}'],
                    [$role_name, $parents_text, $children_text],
                    $this->translate('role_detail_message')
                );

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('add_parent'), 'callback_data' => 'add_parent:' . $role_name],
                            ['text' => $this->translate('remove_child'), 'callback_data' => 'remove_child:' . $role_name],
                        ],
                        [
                            ['text' => $this->translate('assign_role_to_user'), 'callback_data' => 'assign_role_to:' . $role_name],
                        ],
                        [
                            ['text' => $this->translate('delete_this_role'), 'callback_data' => 'confirm_delete_role:' . $role_name],
                        ],
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => 'view_roles'],
                        ],
                    ],
                ];

                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $message_text, $keyboard);
            } elseif (preg_match("/^add_parent:(.+)$/", $payload, $matches)) {
                if (! $this->isGranted('admin')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }

                $role_service = $this->container->get('role_service');
                $role_name = $matches[1];
                $all_roles = $role_service->getAllRolesSorted();
                $existing_parents = $role_service->getParents($role_name);
                $existing_parent_names = array_column($existing_parents, 'role_name');

                $keyboard = ['inline_keyboard' => []];

                foreach ($all_roles as $role) {
                    if ($role['role_name'] === $role_name || in_array($role['role_name'], $existing_parent_names, true)) {
                        continue;
                    }

                    $children = $role_service->getChildren($role['role_name']);
                    $children_text = ! empty($children) ? implode(', ', array_column($children, 'role_name')) : "\u{2014}";

                    $keyboard['inline_keyboard'][] = [
                        [
                            'text' => $role['role_name'],
                            'callback_data' => 'confirm_parent:' . $role['role_name'] . ':' . $role_name,
                        ],
                        [
                            'text' => $children_text,
                            'callback_data' => 'confirm_parent:' . $role['role_name'] . ':' . $role_name,
                        ],
                    ];
                }

                $keyboard['inline_keyboard'][] = [
                    ['text' => $this->translate('go_back'), 'callback_data' => 'show_role:' . $role_name],
                ];

                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], str_replace('{role}', $role_name, $this->translate('select_parent_message')), $keyboard);
            } elseif (preg_match("/^confirm_parent:(.+):(.+)$/", $payload, $matches)) {
                if (! $this->isGranted('admin')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }

                $role_service = $this->container->get('role_service');
                $parent_name = $matches[1];
                $child_name = $matches[2];

                $role_service->addParentChild($parent_name, $child_name);
                $this->callbackAnswer($callback_query_id, str_replace(['{parent}', '{child}'], [$parent_name, $child_name], $this->translate('parent_added_message')));

                // Refresh role detail panel
                $parents = $role_service->getParents($child_name);
                $children = $role_service->getChildren($child_name);
                $parents_text = ! empty($parents) ? implode(', ', array_column($parents, 'role_name')) : "\u{2014}";
                $children_text = ! empty($children) ? implode(', ', array_column($children, 'role_name')) : "\u{2014}";

                $message_text = str_replace(
                    ['{role}', '{parents}', '{children}'],
                    [$child_name, $parents_text, $children_text],
                    $this->translate('role_detail_message')
                );

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('add_parent'), 'callback_data' => 'add_parent:' . $child_name],
                            ['text' => $this->translate('remove_child'), 'callback_data' => 'remove_child:' . $child_name],
                        ],
                        [
                            ['text' => $this->translate('assign_role_to_user'), 'callback_data' => 'assign_role_to:' . $child_name],
                        ],
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => 'view_roles'],
                        ],
                    ],
                ];

                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $message_text, $keyboard);
            } elseif (preg_match("/^remove_child:(.+)$/", $payload, $matches)) {
                if (! $this->isGranted('admin')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }

                $role_service = $this->container->get('role_service');
                $role_name = $matches[1];
                $children = $role_service->getChildren($role_name);

                if (empty($children)) {
                    $this->callbackAnswer($callback_query_id, $this->translate('no_children_message'));
                } else {
                    $keyboard = ['inline_keyboard' => []];

                    foreach ($children as $child) {
                        $keyboard['inline_keyboard'][] = [
                            [
                                'text' => $child['role_name'],
                                'callback_data' => 'confirm_remove_child:' . $role_name . ':' . $child['role_name'],
                            ],
                        ];
                    }

                    $keyboard['inline_keyboard'][] = [
                        ['text' => $this->translate('go_back'), 'callback_data' => 'show_role:' . $role_name],
                    ];

                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], str_replace('{role}', $role_name, $this->translate('select_child_to_remove_message')), $keyboard);
                }
            } elseif (preg_match("/^confirm_remove_child:(.+):(.+)$/", $payload, $matches)) {
                if (! $this->isGranted('admin')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }

                $role_service = $this->container->get('role_service');
                $parent_name = $matches[1];
                $child_name = $matches[2];

                $role_service->removeParentChild($parent_name, $child_name);
                $this->callbackAnswer($callback_query_id, str_replace(['{parent}', '{child}'], [$parent_name, $child_name], $this->translate('child_removed_message')));

                // Refresh to parent's detail panel
                $parents = $role_service->getParents($parent_name);
                $children = $role_service->getChildren($parent_name);
                $parents_text = ! empty($parents) ? implode(', ', array_column($parents, 'role_name')) : "\u{2014}";
                $children_text = ! empty($children) ? implode(', ', array_column($children, 'role_name')) : "\u{2014}";

                $message_text = str_replace(
                    ['{role}', '{parents}', '{children}'],
                    [$parent_name, $parents_text, $children_text],
                    $this->translate('role_detail_message')
                );

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('add_parent'), 'callback_data' => 'add_parent:' . $parent_name],
                            ['text' => $this->translate('remove_child'), 'callback_data' => 'remove_child:' . $parent_name],
                        ],
                        [
                            ['text' => $this->translate('assign_role_to_user'), 'callback_data' => 'assign_role_to:' . $parent_name],
                        ],
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => 'view_roles'],
                        ],
                    ],
                ];

                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $message_text, $keyboard);
            } elseif (preg_match("/^confirm_delete_role:(.+)$/", $payload, $matches)) {
                if (! $this->isGranted('admin')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }

                $role_name = $matches[1];

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('confirm_yes'), 'callback_data' => 'do_delete_role:' . $role_name],
                            ['text' => $this->translate('confirm_no'), 'callback_data' => 'view_roles'],
                        ],
                    ],
                ];

                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], str_replace('{role}', $role_name, $this->translate('confirm_delete_role_message')), $keyboard);
            } elseif (preg_match("/^do_delete_role:(.+)$/", $payload, $matches)) {
                if (! $this->isGranted('admin')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }

                $role_service = $this->container->get('role_service');
                $role_name = $matches[1];

                if ($role_service->deleteRole($role_name)) {
                    $this->callbackAnswer($callback_query_id, str_replace('{role}', $role_name, $this->translate('role_deleted_message')));

                    // Refresh to access control panel
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $this->translate('create_role'), 'callback_data' => 'create_role'],
                                ['text' => $this->translate('delete_role'), 'callback_data' => 'delete_role'],
                            ],
                            [
                                ['text' => $this->translate('view_roles'), 'callback_data' => 'view_roles'],
                            ],
                            [
                                ['text' => $this->translate('go_back'), 'callback_data' => 'control_panel'],
                            ],
                        ],
                    ];
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $this->translate('access_control_panel_message'), $keyboard);
                } else {
                    $this->callbackAnswer($callback_query_id, $this->translate('delete_role_failure_message'));
                }
            } elseif (preg_match("/^assign_role_to:(.+)$/", $payload, $matches)) {
                if (! $this->isGranted('admin')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }

                $role_name = $matches[1];
                $user_state_service->setState($user_id, ['role_name' => $role_name], 'awaiting_user_id_for_role');

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => 'show_role:' . $role_name],
                        ],
                    ],
                ];

                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], str_replace('{role}', $role_name, $this->translate('enter_user_id_for_role_message')), $keyboard);
            } elseif ('create_role' === $payload) {
                if (! $this->isGranted('admin')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }

                $user_state_service->setState($user_id, 'awaiting_role_creation');
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $this->translate('go_back'), 'callback_data' => 'access_control'],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $this->translate('enter_role_name_message'), $keyboard);
            } elseif ('delete_role' === $payload) {
                if (! $this->isGranted('admin')) {
                    $this->sendMessage($chat_id, $this->translate('no_permission_message'));

                    return;
                }

                $role_service = $this->container->get('role_service');
                $all_roles = $role_service->getAllRolesSorted();

                $keyboard = ['inline_keyboard' => []];

                foreach ($all_roles as $role) {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => $role['role_name'], 'callback_data' => 'confirm_delete_role:' . $role['role_name']],
                    ];
                }

                $keyboard['inline_keyboard'][] = [
                    ['text' => $this->translate('go_back'), 'callback_data' => 'access_control'],
                ];

                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $this->translate('select_role_to_delete_message'), $keyboard);
            }
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
        string $prefix = 'author_',
        string $page_prefix = 'page_',
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
            $keyboard['inline_keyboard'][] = [['text' => $this->translate('go_back'), 'callback_data' => 'control_panel']];
        }

        return $keyboard;
    }

    private function translate(string $key)
    {
        return $this->container->get('translator')->translate($key);
    }
}
