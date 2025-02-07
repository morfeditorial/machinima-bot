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

namespace morfeditorial;

class MyBot extends tgLib
{
    private const DATABASE_FILE = __DIR__ . '/../machinimators.db';

    private const TRANSLATIONS_FILE = __DIR__ . '/../translations.json';

    private CommandFactory $command_factory;

    private DependencyContainer $container;

    public function __construct($token)
    {
        parent::__construct($token);

        $translations = json_decode(file_get_contents(self::TRANSLATIONS_FILE), true);
        $this->container = new DependencyContainer($translations, 'en');
        $this->container->set('db_manager', new DatabaseManager(self::DATABASE_FILE));
        $this->container->set('fuzzy_search', new FuzzySearch);
        $this->container->set('visuals_links', [
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
        ]);

        $this->command_factory = new CommandFactory($this, $this->container);
        $this->initializeCommands();
    }
    
    private function initializeCommands() : void
    {
        // Register commands
        $this->command_factory->registerCommand(new \morfeditorial\commands\StartCommand($this, $this->container)); // start
        $this->command_factory->registerCommand(new \morfeditorial\commands\HelpCommand($this, $this->container)); // help
        $this->command_factory->registerCommand(new \morfeditorial\commands\TimeCommand($this, $this->container)); // time
        $this->command_factory->registerCommand(new \morfeditorial\commands\WeatherCommand($this, $this->container)); // weather
        $this->command_factory->registerCommand(new \morfeditorial\commands\WhoisCommand($this, $this->container)); // whois
        $this->command_factory->registerCommand(new \morfeditorial\commands\UpdateCommand($this, $this->container)); // update
        $this->command_factory->registerCommand(new \morfeditorial\commands\AdminPanelCommand($this, $this->container)); // admin_panel
        // $this->command_factory->registerCommand(new \morfeditorial\commands\SearchContentCommand($this, $this->container)); // search_content
        $this->command_factory->registerCommand(new \morfeditorial\commands\SearchAuthorCommand($this, $this->container)); // search_author
        // $this->command_factory->registerCommand(new \morfeditorial\commands\CategoriesCommand($this, $this->container)); // categories
        // $this->command_factory->registerCommand(new \morfeditorial\commands\TopAuthorsCommand($this, $this->container)); // top_authors
        // $this->command_factory->registerCommand(new \morfeditorial\commands\RandomContentCommand($this, $this->container)); // random_content
        $this->command_factory->registerCommand(new \morfeditorial\commands\CreateRoleCommand($this, $this->container)); // create_role
        $this->command_factory->registerCommand(new \morfeditorial\commands\AssignInitialAdminCommand($this, $this->container)); // assign_initial_admin
        $this->command_factory->registerCommand(new \morfeditorial\commands\AssignRoleCommand($this, $this->container)); // assign_role

        $this->command_factory->initializeCommands();
    }

    public function handleUpdate($update) : void
    {
        $message_data = $this->extractMessageData($update);
        $message = $message_data['message'] ?? null;

        $this->container->set('translator', new Translator(json_decode(file_get_contents(self::TRANSLATIONS_FILE), true), $message_data['language_code'] ?? 'en'));

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
                $this->container->get('db_manager')->getCurrentPanel($message_data['user_id']),
                $this->container->get('db_manager')->getCurrentPage($message_data['user_id']),
                $command_data['cmd'],
                $command_data['args']
            );
        } else {
            $this->sendMessage($message_data['chat_id'], $this->container->get('translator')->translate('unknown_command_message'));
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
        $db_manager = $this->container->get('db_manager');
        $translator = $this->container->get('translator');
        $visuals_links = $this->container->get('visuals_links');
        $current_panel = $db_manager->getCurrentPanel($user_id);
        $current_page = $db_manager->getCurrentPage($user_id);
        $default_state = $db_manager->getState($user_id);

        if ('awaiting_author_name_creation' === $default_state) {
            if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                $this->deleteMessage($chat_id, $message_id);
                $db_manager->clearState($user_id, 'default');
                $author_id = $db_manager->createAuthor($message);
                $author_status = $db_manager->isPrivate($author_id);
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $translator->translate('change_name'), 'callback_data' => 'change_name_' . $author_id],
                            ['text' => ($author_status ? $translator->translate('make_public') : $translator->translate('make_private')), 'callback_data' => 'set_private_' . $author_id],
                        ],
                        [
                            ['text' => $translator->translate('add_bio'), 'callback_data' => 'set_author_about_' . $author_id],
                            ['text' => $translator->translate('add_link'), 'callback_data' => 'add_author_link_' . $author_id],
                        ],
                        [
                            ['text' => $translator->translate('delete_this_author'), 'callback_data' => 'author_to_delete_' . $author_id],
                        ],
                        [
                            ['text' => $translator->translate('go_back'), 'callback_data' => 'control_panel'],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[2], str_replace('{author}', htmlspecialchars($message), $translator->translate('author_added_message')), $keyboard);
            } else {
                $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
            }
        } elseif ($state = $db_manager->getState($user_id, 'change_name')) {
            if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                $this->deleteMessage($chat_id, $message_id);
                $db_manager->clearState($user_id, 'change_name');
                $author_id = $state['author_id'];
                $author = $db_manager->getAuthorById($author_id);
                $db_manager->updateAuthorName($author_id, $message);
                $author_status = $db_manager->isPrivate($author_id);
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $translator->translate('change_name'), 'callback_data' => 'change_name_' . $author_id],
                            ['text' => ($author_status ? $translator->translate('make_public') : $translator->translate('make_private')), 'callback_data' => 'set_private_' . $author_id],
                        ],
                        [
                            ['text' => $translator->translate('change_bio'), 'callback_data' => 'set_author_about_' . $author_id],
                            ['text' => ($author['channel_link'] ? $translator->translate('change_link') : $translator->translate('add_link')), 'callback_data' => 'add_author_link_' . $author_id],
                        ],
                        [
                            ['text' => $translator->translate('delete_this_author'), 'callback_data' => 'author_to_delete_' . $author_id],
                        ],
                        [
                            ['text' => $translator->translate('go_back'), 'callback_data' => $current_page ?? 'control_panel'],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[6], str_replace(['{author}', '{oldName}', '{biography}', '{link}'], [htmlspecialchars($message), htmlspecialchars($author['name']), ($author['biography'] ? htmlspecialchars($author['biography']) : $translator->translate('bio_not_set')), ($author['channel_link'] ? htmlspecialchars($author['channel_link']) : $translator->translate('link_not_set'))], $translator->translate('name_changed_message')), $keyboard);
            } else {
                $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
            }
        } elseif ($state = $db_manager->getState($user_id, 'set_author_about')) {
            if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                $this->deleteMessage($chat_id, $message_id);
                $db_manager->clearState($user_id, 'set_author_about');
                $author_id = $state['author_id'];
                $author = $db_manager->getAuthorById($author_id);
                $db_manager->setBiography($author_id, $message);
                $author_status = $db_manager->isPrivate($author_id);
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $translator->translate('change_name'), 'callback_data' => 'change_name_' . $author_id],
                            ['text' => ($author_status ? $translator->translate('make_public') : $translator->translate('make_private')), 'callback_data' => 'set_private_' . $author_id],
                        ],
                        [
                            ['text' => $translator->translate('change_bio'), 'callback_data' => 'set_author_about_' . $author_id],
                            ['text' => ($author['channel_link'] ? $translator->translate('change_link') : $translator->translate('add_link')), 'callback_data' => 'add_author_link_' . $author_id],
                        ],
                        [
                            ['text' => $translator->translate('delete_this_author'), 'callback_data' => 'author_to_delete_' . $author_id],
                        ],
                        [
                            ['text' => $translator->translate('go_back'), 'callback_data' => $current_page ?? 'control_panel'],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, ($author['biography'] ? $visuals_links[8] : $visuals_links[7]), str_replace(['{author}', '{biography}', '{link}'], [htmlspecialchars($author['name']), htmlspecialchars($message), ($author['channel_link'] ? htmlspecialchars($author['channel_link']) : $translator->translate('link_not_set'))], ($author['biography'] ? $translator->translate('bio_changed_message') : $translator->translate('bio_added_message'))), $keyboard);
            } else {
                $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
            }
        } elseif ($state = $db_manager->getState($user_id, 'add_author_link')) {
            if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                $this->deleteMessage($chat_id, $message_id);
                $db_manager->clearState($user_id, 'add_author_link');
                $author_id = $state['author_id'];
                $author = $db_manager->getAuthorById($author_id);
                $db_manager->setChannelLink($author_id, $message);
                $author_status = $db_manager->isPrivate($author_id);
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => $translator->translate('change_name'), 'callback_data' => 'change_name_' . $author_id],
                            ['text' => ($author_status ? $translator->translate('make_public') : $translator->translate('make_private')), 'callback_data' => 'set_private_' . $author_id],
                        ],
                        [
                            ['text' => ($author['biography'] ? $translator->translate('change_bio') : $translator->translate('add_bio')), 'callback_data' => 'set_author_about_' . $author_id],
                            ['text' => $translator->translate('change_link'), 'callback_data' => 'add_author_link_' . $author_id],
                        ],
                        [
                            ['text' => $translator->translate('delete_this_author'), 'callback_data' => 'author_to_delete_' . $author_id],
                        ],
                        [
                            ['text' => $translator->translate('go_back'), 'callback_data' => $current_page ?? 'control_panel'],
                        ],
                    ],
                ];
                $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], str_replace(['{author}', '{biography}', '{link}'], [htmlspecialchars($author['name']), ($author['biography'] ? htmlspecialchars($author['biography']) : $translator->translate('bio_not_set')), htmlspecialchars($message)], $translator->translate('link_changed_message')), $keyboard);
            } else {
                $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
            }
        } elseif ('awaiting_role_creation' === $default_state) {
            $parts = explode(' ', $message);
            if (2 === count($parts)) {
                $role_name = $parts[0];
                $priority = intval($parts[1]);
                try {
                    $this->createRole($role_name, $priority);
                    $this->sendMessage($chat_id, 'Роль ' . $role_name . ' з пріоритетом ' . $priority . ' була створена.');
                } catch (Exception $e) {
                    $this->sendMessage($chat_id, $e->getMessage());
                }
            } else {
                $this->sendMessage($chat_id, 'Неправильний формат. Використовуйте: <code>назва_ролі пріоритет</code>');
            }
            $db_manager->clearState($user_id, 'default');
        } elseif ('awaiting_role_deletion' === $default_state) {
            $role_name = $message;
            $db_manager->deleteRole($role_name);
            $this->sendMessage($chat_id, 'Роль ' . $role_name . ' була видалена.');
            $db_manager->clearState($user_id, 'default');
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
        $db_manager = $this->container->get('db_manager');
        $translator = $this->container->get('translator');
        $visuals_links = $this->container->get('visuals_links');

        if (null !== $payload) {
            $current_panel = $db_manager->getCurrentPanel($user_id);
            $current_page = $db_manager->getCurrentPage($user_id);

            if ('control_panel' === $payload) {
                if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                    $db_manager->clearState($user_id);
                    if (! is_null($current_page)) {
                        $db_manager->resetCurrentPage($user_id);
                    }
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $translator->translate('add_author'), 'callback_data' => 'add_author'],
                                ['text' => $translator->translate('delete_author'), 'callback_data' => 'delete_author'],
                            ],
                            [
                                ['text' => $translator->translate('list_of_authors'), 'callback_data' => 'list_of_authors'],
                            ],
                            [
                                ['text' => $translator->translate('access_control'), 'callback_data' => 'access_control'],
                            ],
                        ],
                    ];
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $translator->translate('admin_panel_message'), $keyboard);
                } else {
                    $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
                }
            } elseif ('add_author' === $payload) {
                if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                    $db_manager->setState($user_id, 'awaiting_author_name_creation');
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $translator->translate('go_back'), 'callback_data' => 'control_panel'],
                            ],
                        ],
                    ];
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[10], $translator->translate('add_author_message'), $keyboard);
                } else {
                    $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
                }
            } elseif ('delete_author' === $payload) {
                if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                    $db_manager->setCurrentPage($user_id, 'delete_page_1');
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $translator->translate('delete_author_message'), $this->generateAuthorsKeyboard(1, 3, 1, 'author_to_delete_', 'delete_page_'));
                } else {
                    $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
                }
            } elseif (preg_match("/^delete_page_(\d+)$/", $payload, $matches)) {
                if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                    $db_manager->setCurrentPage($user_id, $payload);
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $translator->translate('delete_author_message'), $this->generateAuthorsKeyboard($matches[1], 3, 1, 'author_to_delete_', 'delete_page_'));
                } else {
                    $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
                }
            } elseif (preg_match("/^author_to_delete_(\d+)$/", $payload, $matches)) {
                if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                    preg_match("/^delete_page_(\d+)$/", $current_page ?? 'page_', $prefix);
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $translator->translate('confirm_delete'), 'callback_data' => 'delete_confirmation_' . $matches[1]],
                            ],
                            [
                                ['text' => $translator->translate('go_back'), 'callback_data' => $prefix[0] ?? 'author_' . $matches[1]],
                            ],
                        ],
                    ];
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], str_replace('{author}', htmlspecialchars($db_manager->getAuthorById(intval($matches[1]))['name']), $translator->translate('confirm_delete_message')), $keyboard);
                } else {
                    $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
                }
            } elseif (preg_match("/^delete_confirmation_(\d+)$/", $payload, $matches)) {
                if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                    $author = $db_manager->getAuthorById(intval($matches[1]));
                    $this->deleteAuthor($matches[1]);
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $translator->translate('go_back'), 'callback_data' => $current_page ?? 'control_panel'],
                            ],
                        ],
                    ];
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], str_replace('{author}', htmlspecialchars($author['name']), $translator->translate('author_deleted_message')), $keyboard);
                } else {
                    $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
                }
            } elseif (preg_match("/^change_name_(\d+)$/", $payload, $matches)) {
                if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                    $db_manager->setState($user_id, ['author_id' => intval($matches[1])], 'change_name');
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $translator->translate('go_back'), 'callback_data' => 'author_' . $matches[1]],
                            ],
                        ],
                    ];
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[3], $translator->translate('pending_name_change'), $keyboard);
                } else {
                    $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
                }
            } elseif (preg_match("/^set_author_about_(\d+)$/", $payload, $matches)) {
                if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                    $author = $db_manager->getAuthorById(intval($matches[1]));
                    $db_manager->setState($user_id, ['author_id' => intval($matches[1])], 'set_author_about');
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $translator->translate('go_back'), 'callback_data' => 'author_' . $matches[1]],
                            ],
                        ],
                    ];
                    $this->editMediaMessage($chat_id, $current_panel, ($author['biography'] ? $visuals_links[5] : $visuals_links[4]), ($author['biography'] ? $translator->translate('pending_bio_change') : $translator->translate('pending_bio_add')), $keyboard);
                } else {
                    $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
                }
            } elseif (preg_match("/^add_author_link_(\d+)$/", $payload, $matches)) {
                if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                    $db_manager->setState($user_id, ['author_id' => intval($matches[1])], 'add_author_link');
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $translator->translate('go_back'), 'callback_data' => 'author_' . $matches[1]],
                            ],
                        ],
                    ];
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $translator->translate('pending_link_change'), $keyboard);
                } else {
                    $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
                }
            } elseif ('list_of_authors' === $payload) {
                if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                    $db_manager->setCurrentPage($user_id, 'page_1');
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[9], $translator->translate('list_of_authors_message'), $this->generateAuthorsKeyboard());
                } else {
                    $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
                }
            } elseif (preg_match("/^page_(\d+)$/", $payload, $matches)) {
                if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                    $db_manager->setCurrentPage($user_id, $payload);
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[9], $translator->translate('list_of_authors_message'), $this->generateAuthorsKeyboard($matches[1]));
                } else {
                    $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
                }
            } elseif (preg_match("/^author_(\d+)$/", $payload, $matches)) {
                if ($db_manager->hasHigherRole($user_id, 'moderator')) {
                    $author = $db_manager->getAuthorById(intval($matches[1]));
                    if (false !== $author) {
                        $author_status = $db_manager->isPrivate(intval($matches[1]));
                        $keyboard = [
                            'inline_keyboard' => [
                                [
                                    ['text' => $translator->translate('change_name'), 'callback_data' => 'change_name_' . $matches[1]],
                                    ['text' => ($author_status ? $translator->translate('make_public') : $translator->translate('make_private')), 'callback_data' => 'set_private_' . $matches[1]],
                                ],
                                [
                                    ['text' => ($author['biography'] ? $translator->translate('change_bio') : $translator->translate('add_bio')), 'callback_data' => 'set_author_about_' . $matches[1]],
                                    ['text' => ($author['channel_link'] ? $translator->translate('change_link') : $translator->translate('add_link')), 'callback_data' => 'add_author_link_' . $matches[1]],
                                ],
                                [
                                    ['text' => $translator->translate('delete_this_author'), 'callback_data' => 'author_to_delete_' . $matches[1]],
                                ],
                                [
                                    ['text' => $translator->translate('go_back'), 'callback_data' => $current_page ?? 'control_panel'],
                                ],
                            ],
                        ];
                        $this->editMediaMessage($chat_id, $current_panel, $visuals_links[11], str_replace(['{author}', '{biography}', '{link}'], [htmlspecialchars($author['name']), ($author['biography'] ? htmlspecialchars($author['biography']) : $translator->translate('bio_not_set')), ($author['channel_link'] ? htmlspecialchars($author['channel_link']) : $translator->translate('link_not_set'))], $translator->translate('author_info_message')), $keyboard);

                        return;
                    }
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $translator->translate('go_back'), 'callback_data' => 'control_panel'],
                            ],
                        ],
                    ];
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $translator->translate('author_not_found_message'), $keyboard);
                } else {
                    $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
                }
            } elseif (preg_match("/^profile_author_(\d+)$/", $payload, $matches)) {
                $author = $db_manager->getAuthorById(intval($matches[1]));
                if (false !== $author) {
                    $avatar = $this->createAvatar($author['name'], $author['channel_link']);
                    $this->pictureReply($chat_id, str_replace(['{author}', '{biography}', '{link}'], [htmlspecialchars($author['name']), ($author['biography'] ? htmlspecialchars($author['biography']) : $translator->translate('bio_not_set')), ($author['channel_link'] ? htmlspecialchars($author['channel_link']) : $translator->translate('link_not_set'))], $translator->translate('author_info_message')), $avatar);
                    unlink($avatar);

                    return;
                }
                $this->sendMessage($chat_id, $translator->translate('author_not_found_message'));
            } elseif ('access_control' === $payload) {
                if ($db_manager->hasHigherRole($user_id, 'admin')) {
                    $db_manager->clearState($user_id);
                    if (! is_null($current_page)) {
                        $db_manager->resetCurrentPage($user_id);
                    }
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $translator->translate('create_role'), 'callback_data' => 'create_role'],
                                ['text' => $translator->translate('delete_role'), 'callback_data' => 'delete_role'],
                            ],
                            [
                                ['text' => $translator->translate('view_roles'), 'callback_data' => 'view_roles'],
                                ['text' => $translator->translate('change_priorities'), 'callback_data' => 'change_priorities'],
                            ],
                            [
                                ['text' => $translator->translate('go_back'), 'callback_data' => 'control_panel'],
                            ],
                        ],
                    ];
                    $this->editMediaMessage($chat_id, $current_panel, $visuals_links[1], $translator->translate('access_control_panel_message'), $keyboard);
                } else {
                    $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
                }
            } elseif ('change_priorities' === $payload) {
                $this->sendUpdateRolesPriorityPanel($chat_id, $user_id, $callback_query_id);
            } elseif (0 === strpos($payload, 'select_role:')) {
                [, $role_name, $role_level] = explode(':', $payload);
                $this->toggleRoleSelection($chat_id, $user_id, $callback_query_id, $role_name, $role_level);
            } elseif (0 === strpos($payload, 'noop:')) {
                if ($selected_role = $db_manager->getState($user_id, 'selected_role')) {
                    [, $priority] = explode(':', $payload);
                    $this->updateRolePriorityAndLevel($chat_id, $user_id, $callback_query_id, $selected_role['role_name'], $selected_role['role_level'], null, 'secondary', (int) $priority);
                }
            }
        }
    }

    private function extractMessageData($data)
    {
        $callbackQuery = $data['callback_query'] ?? null;

        return [
            'message' => $callbackQuery['message']['text'] ?? $data['message']['text'] ?? null,
            'message_id' => $callbackQuery['message']['message_id'] ?? $data['message']['message_id'] ?? null,
            'chat_type' => $callbackQuery['message']['chat']['type'] ?? $data['message']['chat']['type'] ?? null,
            'chat_id' => $callbackQuery['message']['chat']['id'] ?? $data['message']['chat']['id'] ?? null,
            'user_id' => $callbackQuery['message']['from']['id'] ?? $data['message']['from']['id'] ?? null,
            'language_code' => $callbackQuery['from']['language_code'] ?? $data['message']['from']['language_code'] ?? null,
            'payload' => $callbackQuery['data'] ?? null,
            'callback_query_id' => $callbackQuery['id'] ?? null,
            'reply_message_id' => $data['message']['reply_to_message']['message_id'] ?? null,
            'reply_author' => $data['message']['reply_to_message']['from']['id'] ?? null,
            'first_name' => $data['message']['from']['first_name'] ?? null,
        ];
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

    public function generateAuthorsKeyboard($page_number = 1, $buttons_per_page = 3, $authors_per_row = 1, $prefix = 'author_', $page_prefix = 'page_', $authors = null) : array
    {
        $initial_authors = $authors;
        $authors = $authors ?? $db_manager->getAllAuthors();
        $total_buttons = count($authors);
        $total_pages = (int) ceil($total_buttons / $buttons_per_page);
        $page_number = max(1, min($page_number, $total_pages));
        $offset = ($page_number - 1) * $buttons_per_page;
        $keyboard = ['inline_keyboard' => []];
        $current_row = [];
        $row_count = 0;
        $sliced_authors = array_slice($authors, $offset, $buttons_per_page);

        foreach ($sliced_authors as $author) {
            $current_row[] = ['text' => $author['name'], 'callback_data' => $prefix . $author['id']];
            $row_count++;

            if ($row_count === $authors_per_row) {
                $keyboard['inline_keyboard'][] = $current_row;
                $current_row = [];
                $row_count = 0;
            }
        }

        $translator = $this->container->get('translator');

        if (! empty($current_row)) {
            $keyboard['inline_keyboard'][] = $current_row;
        }

        if ($total_pages > 1) {
            $pagination = [];

            if ($page_number > 1) {
                $pagination[] = ['text' => $translator->translate('previous_page'), 'callback_data' => $page_prefix . ($page_number - 1)];
            }

            if ($page_number < $total_pages) {
                $pagination[] = ['text' => $translator->translate('next_page'), 'callback_data' => $page_prefix . ($page_number + 1)];
            }

            $keyboard['inline_keyboard'][] = $pagination;
        }

        if (is_null($initial_authors)) {
            $keyboard['inline_keyboard'][] = [['text' => $translator->translate('go_back'), 'callback_data' => 'control_panel']];
        }

        return $keyboard;
    }

    public function sendUpdateRolesPriorityPanel(int $chat_id, int $user_id, $callback_query_id) : void
    {
        $db_manager = $this->container->get('db_manager');
        $translator = $this->container->get('translator');
        $visuals_links = $this->container->get('visuals_links');

        if ($db_manager->hasHigherRole($user_id, 'admin')) {
            $roles = $db_manager->queryRolesOrderedByPriority();
            $selected_role = $db_manager->getState($user_id, 'selected_role');

            $keyboard = ['inline_keyboard' => []];

            foreach ($roles as $role) {
                $keyboard_row = [];
                $this->buildRoleButton($keyboard_row, $selected_role, $role);
                $keyboard['inline_keyboard'][] = $keyboard_row;
            }

            $keyboard['inline_keyboard'][] = [
                ['text' => $translator->translate('go_back'), 'callback_data' => 'access_control'],
            ];

            $this->editMediaMessage($chat_id, $db_manager->getCurrentPanel($user_id), $visuals_links[1], 'Ви можете змінити пріоритети ролей:', $keyboard);
        } else {
            $this->sendMessage($chat_id, $translator->translate('no_permission_message'));
        }
    }

    public function toggleRoleSelection(int $chat_id, int $user_id, mixed $callback_query_id, string $role_name) : void
    {
        $db_manager = $this->container->get('db_manager');
        $selected_role = $db_manager->getState($user_id, 'selected_role');

        if ($selected_role && $selected_role['role_name'] === $role_name) {
            $db_manager->clearState($user_id, 'selected_role');
            $this->callbackAnswer($callback_query_id, 'Ви зняли виділення з ролі «' . $role_name . '».');
        } elseif ($selected_role) {
            $this->updateRolePriority($chat_id, $user_id, $callback_query_id, $selected_role['role_name'], $role_name);
        } else {
            $db_manager->setState($user_id, ['role_name' => $role_name], 'selected_role');
            $this->callbackAnswer($callback_query_id, 'Ви вибрали роль «' . $role_name . '» для зміни пріоритету.');
        }

        $this->sendUpdateRolesPriorityPanel($chat_id, $user_id, $callback_query_id);
    }

    private function buildRoleButton(array &$keyboard_row, ?array $selected_role, array $role) : void
    {
        $button_text = ($selected_role && $selected_role['role_name'] === $role['role_name']) ?
            "✔ {$role['role_name']} ({$role['priority']})" :
            "{$role['role_name']} ({$role['priority']})";
        $keyboard_row[] = [
            'text' => $button_text,
            'callback_data' => "select_role:{$role['role_name']}",
        ];
    }

    public function updateRolePriority(int $chat_id, int $user_id, mixed $callback_query_id, string $selected_role_name, string $target_role_name) : void
    {
        $db_manager = $this->container->get('db_manager');
        $target_priority = $db_manager->getRolePriority($target_role_name);

        $db_manager->updateRolePriorities($selected_role_name, $target_priority);

        $db_manager->clearState($user_id, 'selected_role');
        $this->callbackAnswer($callback_query_id, 'Роль «' . $selected_role_name . '» оновлена.');
        $this->sendUpdateRolesPriorityPanel($chat_id, $user_id, $callback_query_id);
    }
}
