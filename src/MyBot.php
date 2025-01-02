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

    private CommandFactory $commandFactory;

    private DependencyContainer $container;

    public function __construct($token)
    {
        parent::__construct($token);

        $this->container = new DependencyContainer();
        $this->container->set('dbManager', new DatabaseManager(self::DATABASE_FILE));
        $this->container->set('fuzzySearch', new FuzzySearch());
        $this->container->set('visualsLinks', [
            "https://i.ibb.co/mC7sv0W/01.png",
            "https://i.ibb.co/ygqgFMV/02.png",
            "https://i.ibb.co/1KysC55/03.png",
            "https://i.ibb.co/64vFVfS/04.png",
            "https://i.ibb.co/TLDzmVf/05.png",
            "https://i.ibb.co/85LB6PW/06.png",
            "https://i.ibb.co/fDbmdMM/07.png",
            "https://i.ibb.co/tqMNGtX/08.png",
            "https://i.ibb.co/xF1QQXk/09.png",
            "https://i.ibb.co/LPYBSBX/10.png",
            "https://i.ibb.co/Fn2HJJQ/11.png",
            "https://i.ibb.co/TYPWsLQ/12.png"
        ]);

        $this->commandFactory = new CommandFactory($this, $this->container);
    }

    public function handleUpdate($update) : void
    {
        $messageData = $this->extractMessageData($update);
        $message = $messageData['message'] ?? null;

        $this->container->set('translator', new Translator(json_decode(file_get_contents(self::TRANSLATIONS_FILE), true), $messageData["language"] ?? "en"));

        if ($message) {
            $this->processMessage($messageData, $message);
        } else {
            $this->handlePanels($messageData);
        }
    }

    private function processMessage(array $messageData, string $message) : void
    {
        $commandData = $this->parseCommand($message);
        if ($commandData) {
            $this->executeCommand($commandData, $messageData);
        } else {
            $this->handleStates($messageData);
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

    private function executeCommand(array $commandData, array $messageData) : void
    {
        $command = $this->commandFactory->create($commandData['cmd']);
        if ($command instanceof CommandInterface) {
            $command->execute(
                $messageData['message'],
                $messageData['messageId'],
                $messageData['chatType'],
                $messageData['chatId'],
                $messageData['userId'],
                $messageData['payload'],
                $messageData['replyMessageId'],
                $messageData['replyAuthor'],
                $messageData['firstName'],
                $this->container->get('dbManager')->getCurrentPanel($messageData['userId']),
                $this->container->get('dbManager')->getCurrentPage($messageData['userId']),
                $commandData['cmd'],
                $commandData['args']
            );
        } else {
            $this->sendMessage($messageData['chatId'], $this->container->get('translator')->translate("unknown_command_message"));
        }
    }

    private function handleStates($messageData)
    {
        $message = $messageData["message"];
        $messageId = $messageData["messageId"];
        $chatType = $messageData["chatType"];
        $chatId = $messageData["chatId"];
        $userId = $messageData["userId"] !== $chatId ? $chatId : $messageData["userId"];
        $payload = $messageData["payload"];
        $firstName = $messageData["firstName"];
        $dbManager = $this->container->get('dbManager');
        $translator = $this->container->get('translator');
        $visualsLinks = $this->container->get('visualsLinks');
        $currentPanel = $dbManager->getCurrentPanel($userId);
        $currentPage = $dbManager->getCurrentPage($userId);
        $defaultState = $dbManager->getState($userId);

        if ("awaiting_author_name_creation" === $defaultState) {
            if ($dbManager->hasHigherRole($userId, "moderator")) {
                $this->deleteMessage($chatId, $messageId);
                $dbManager->clearState($userId, "default");
                $authorId = $dbManager->createAuthor($message);
                $authorStatus = $dbManager->isPrivate($authorId);
                $keyboard = [
                    "inline_keyboard" => [
                        [
                            ["text" => $translator->translate("change_name"), "callback_data" => "change_name_" . $authorId],
                            ["text" => ($authorStatus ? $translator->translate("make_public") : $translator->translate("make_private")), "callback_data" => "set_private_" . $authorId]
                        ],
                        [
                            ["text" => $translator->translate("add_bio"), "callback_data" => "set_author_about_" . $authorId],
                            ["text" => $translator->translate("add_link"), "callback_data" => "add_author_link_" . $authorId]
                        ],
                        [
                            ["text" => $translator->translate("delete_this_author"), "callback_data" => "author_to_delete_" . $authorId]
                        ],
                        [
                            ["text" => $translator->translate("go_back"), "callback_data" => "control_panel"]
                        ]
                    ]
                ];
                $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[2], str_replace("{author}", htmlspecialchars($message), $translator->translate("author_added_message")), $keyboard);
            } else {
                $this->sendMessage($chatId, $translator->translate("no_permission_message"));
            }
        } elseif ($state = $dbManager->getState($userId, "change_name")) {
            if ($dbManager->hasHigherRole($userId, "moderator")) {
                $this->deleteMessage($chatId, $messageId);
                $dbManager->clearState($userId, "change_name");
                $authorId = $state["author_id"];
                $author = $dbManager->getAuthorById($authorId);
                $dbManager->updateAuthorName($authorId, $message);
                $authorStatus = $dbManager->isPrivate($authorId);
                $keyboard = [
                    "inline_keyboard" => [
                        [
                            ["text" => $translator->translate("change_name"), "callback_data" => "change_name_" . $authorId],
                            ["text" => ($authorStatus ? $translator->translate("make_public") : $translator->translate("make_private")), "callback_data" => "set_private_" . $authorId]
                        ],
                        [
                            ["text" => $translator->translate("change_bio"), "callback_data" => "set_author_about_" . $authorId],
                            ["text" => ($author["channel_link"] ? $translator->translate("change_link") : $translator->translate("add_link")), "callback_data" => "add_author_link_" . $authorId]
                        ],
                        [
                            ["text" => $translator->translate("delete_this_author"), "callback_data" => "author_to_delete_" . $authorId]
                        ],
                        [
                            ["text" => $translator->translate("go_back"), "callback_data" => $currentPage ?? "control_panel"]
                        ]
                    ]
                ];
                $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[6], str_replace(["{author}", "{oldName}", "{biography}", "{link}"], [htmlspecialchars($message), htmlspecialchars($author["name"]), ($author["biography"] ? htmlspecialchars($author["biography"]) : $translator->translate("bio_not_set")), ($author["channel_link"] ? htmlspecialchars($author["channel_link"]) : $translator->translate("link_not_set"))], $translator->translate("name_changed_message")), $keyboard);
            } else {
                $this->sendMessage($chatId, $translator->translate("no_permission_message"));
            }
        } elseif ($state = $dbManager->getState($userId, "set_author_about")) {
            if ($dbManager->hasHigherRole($userId, "moderator")) {
                $this->deleteMessage($chatId, $messageId);
                $dbManager->clearState($userId, "set_author_about");
                $authorId = $state["author_id"];
                $author = $dbManager->getAuthorById($authorId);
                $dbManager->setBiography($authorId, $message);
                $authorStatus = $dbManager->isPrivate($authorId);
                $keyboard = [
                    "inline_keyboard" => [
                        [
                            ["text" => $translator->translate("change_name"), "callback_data" => "change_name_" . $authorId],
                            ["text" => ($authorStatus ? $translator->translate("make_public") : $translator->translate("make_private")), "callback_data" => "set_private_" . $authorId]
                        ],
                        [
                            ["text" => $translator->translate("change_bio"), "callback_data" => "set_author_about_" . $authorId],
                            ["text" => ($author["channel_link"] ? $translator->translate("change_link") : $translator->translate("add_link")), "callback_data" => "add_author_link_" . $authorId]
                        ],
                        [
                            ["text" => $translator->translate("delete_this_author"), "callback_data" => "author_to_delete_" . $authorId]
                        ],
                        [
                            ["text" => $translator->translate("go_back"), "callback_data" => $currentPage ?? "control_panel"]
                        ]
                    ]
                ];
                $this->editMediaMessage($chatId, $currentPanel, ($author["biography"] ? $visualsLinks[8] : $visualsLinks[7]), str_replace(["{author}", "{biography}", "{link}"], [htmlspecialchars($author["name"]), htmlspecialchars($message), ($author["channel_link"] ? htmlspecialchars($author["channel_link"]) : $translator->translate("link_not_set"))], ($author["biography"] ? $translator->translate("bio_changed_message") : $translator->translate("bio_added_message"))), $keyboard);
            } else {
                $this->sendMessage($chatId, $translator->translate("no_permission_message"));
            }
        } elseif ($state = $dbManager->getState($userId, "add_author_link")) {
            if ($dbManager->hasHigherRole($userId, "moderator")) {
                $this->deleteMessage($chatId, $messageId);
                $dbManager->clearState($userId, "add_author_link");
                $authorId = $state["author_id"];
                $author = $dbManager->getAuthorById($authorId);
                $dbManager->setChannelLink($authorId, $message);
                $authorStatus = $dbManager->isPrivate($authorId);
                $keyboard = [
                    "inline_keyboard" => [
                        [
                            ["text" => $translator->translate("change_name"), "callback_data" => "change_name_" . $authorId],
                            ["text" => ($authorStatus ? $translator->translate("make_public") : $translator->translate("make_private")), "callback_data" => "set_private_" . $authorId]
                        ],
                        [
                            ["text" => ($author["biography"] ? $translator->translate("change_bio") : $translator->translate("add_bio")), "callback_data" => "set_author_about_" . $authorId],
                            ["text" => $translator->translate("change_link"), "callback_data" => "add_author_link_" . $authorId]
                        ],
                        [
                            ["text" => $translator->translate("delete_this_author"), "callback_data" => "author_to_delete_" . $authorId]
                        ],
                        [
                            ["text" => $translator->translate("go_back"), "callback_data" => $currentPage ?? "control_panel"]
                        ]
                    ]
                ];
                $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[1], str_replace(["{author}", "{biography}", "{link}"], [htmlspecialchars($author["name"]), ($author["biography"] ? htmlspecialchars($author["biography"]) : $translator->translate("bio_not_set")), htmlspecialchars($message)], $translator->translate("link_changed_message")), $keyboard);
            } else {
                $this->sendMessage($chatId, $translator->translate("no_permission_message"));
            }
        } elseif ("awaiting_role_creation" === $defaultState) {
            $parts = explode(" ", $message);
            if (2 === count($parts)) {
                $roleName = $parts[0];
                $priority = intval($parts[1]);
                try {
                    $this->createRole($roleName, $priority);
                    $this->sendMessage($chatId, "Роль " . $roleName . " з пріоритетом " . $priority . " була створена.");
                } catch (Exception $e) {
                    $this->sendMessage($chatId, $e->getMessage());
                }
            } else {
                $this->sendMessage($chatId, "Неправильний формат. Використовуйте: <code>назва_ролі пріоритет</code>");
            }
            $dbManager->clearState($userId, "default");
        } elseif ("awaiting_role_deletion" === $defaultState) {
            $roleName = $message;
            $dbManager->deleteRole($roleName);
            $this->sendMessage($chatId, "Роль " . $roleName . " була видалена.");
            $dbManager->clearState($userId, "default");
        }
    }

    private function handlePanels($messageData)
    {
        $message = $messageData["message"];
        $messageId = $messageData["messageId"];
        $chatType = $messageData["chatType"];
        $chatId = $messageData["chatId"];
        $userId = $messageData["userId"] !== $chatId ? $chatId : $messageData["userId"];
        $payload = $messageData["payload"];
        $callbackQueryId = $messageData["callbackQueryId"];
        $firstName = $messageData["firstName"];
        $dbManager = $this->container->get('dbManager');
        $translator = $this->container->get('translator');
        $visualsLinks = $this->container->get('visualsLinks');

        if (null !== $payload) {
            $currentPanel = $dbManager->getCurrentPanel($userId);
            $currentPage = $dbManager->getCurrentPage($userId);

            if ("control_panel" === $payload) {
                if ($dbManager->hasHigherRole($userId, "moderator")) {
                    $dbManager->clearState($userId);
                    if (! is_null($currentPage)) {
                        $dbManager->resetCurrentPage($userId);
                    }
                    $keyboard = [
                       "inline_keyboard" => [
                            [
                                ["text" => $translator->translate("add_author"), "callback_data" => "add_author"],
                                ["text" => $translator->translate("delete_author"), "callback_data" => "delete_author"]
                            ],
                            [
                                ["text" => $translator->translate("list_of_authors"), "callback_data" => "list_of_authors"]
                            ],
                            [
                                ["text" => $translator->translate("access_control"), "callback_data" => "access_control"]
                            ]
                        ]
                    ];
                    $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[1], $translator->translate("admin_panel_message"), $keyboard);
                } else {
                    $this->sendMessage($chatId, $translator->translate("no_permission_message"));
                }
            } elseif ("add_author" === $payload) {
                if ($dbManager->hasHigherRole($userId, "moderator")) {
                    $dbManager->setState($userId, "awaiting_author_name_creation");
                    $keyboard = [
                        "inline_keyboard" => [
                            [
                                ["text" => $translator->translate("go_back"), "callback_data" => "control_panel"]
                            ]
                        ]
                    ];
                    $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[10], $translator->translate("add_author_message"), $keyboard);
                } else {
                    $this->sendMessage($chatId, $translator->translate("no_permission_message"));
                }
            } elseif ("delete_author" === $payload) {
                if ($dbManager->hasHigherRole($userId, "moderator")) {
                    $dbManager->setCurrentPage($userId, "delete_page_1");
                    $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[1], $translator->translate("delete_author_message"), $this->generateAuthorsKeyboard(1, 3, 1, "author_to_delete_", "delete_page_"));
                } else {
                    $this->sendMessage($chatId, $translator->translate("no_permission_message"));
                }
            } elseif (preg_match("/^delete_page_(\d+)$/", $payload, $matches)) {
                if ($dbManager->hasHigherRole($userId, "moderator")) {
                    $dbManager->setCurrentPage($userId, $payload);
                    $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[1], $translator->translate("delete_author_message"), $this->generateAuthorsKeyboard($matches[1], 3, 1, "author_to_delete_", "delete_page_"));
                } else {
                    $this->sendMessage($chatId, $translator->translate("no_permission_message"));
                }
            } elseif (preg_match("/^author_to_delete_(\d+)$/", $payload, $matches)) {
                if ($dbManager->hasHigherRole($userId, "moderator")) {
                    preg_match("/^delete_page_(\d+)$/", $currentPage ?? "page_", $prefix);
                    $keyboard = [
                        "inline_keyboard" => [
                            [
                                ["text" => $translator->translate("confirm_delete"), "callback_data" => "delete_confirmation_" . $matches[1]]
                            ],
                            [
                                ["text" => $translator->translate("go_back"), "callback_data" => $prefix[0] ?? "author_" . $matches[1]]
                            ]
                        ]
                    ];
                    $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[1], str_replace("{author}", htmlspecialchars($dbManager->getAuthorById(intval($matches[1]))["name"]), $translator->translate("confirm_delete_message")), $keyboard);
                } else {
                    $this->sendMessage($chatId, $translator->translate("no_permission_message"));
                }
            } elseif (preg_match("/^delete_confirmation_(\d+)$/", $payload, $matches)) {
                if ($dbManager->hasHigherRole($userId, "moderator")) {
                    $author = $dbManager->getAuthorById(intval($matches[1]));
                    $this->deleteAuthor($matches[1]);
                    $keyboard = [
                        "inline_keyboard" => [
                            [
                                ["text" => $translator->translate("go_back"), "callback_data" => $currentPage ?? "control_panel"]
                            ]
                        ]
                    ];
                    $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[1], str_replace("{author}", htmlspecialchars($author["name"]), $translator->translate("author_deleted_message")), $keyboard);
                } else {
                    $this->sendMessage($chatId, $translator->translate("no_permission_message"));
                }
            } elseif (preg_match("/^change_name_(\d+)$/", $payload, $matches)) {
                if ($dbManager->hasHigherRole($userId, "moderator")) {
                    $dbManager->setState($userId, ["author_id" => intval($matches[1])], "change_name");
                    $keyboard = [
                        "inline_keyboard" => [
                            [
                                ["text" => $translator->translate("go_back"), "callback_data" => "author_" . $matches[1]]
                            ]
                        ]
                    ];
                    $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[3], $translator->translate("pending_name_change"), $keyboard);
                } else {
                    $this->sendMessage($chatId, $translator->translate("no_permission_message"));
                }
            } elseif (preg_match("/^set_author_about_(\d+)$/", $payload, $matches)) {
                if ($dbManager->hasHigherRole($userId, "moderator")) {
                    $author = $dbManager->getAuthorById(intval($matches[1]));
                    $dbManager->setState($userId, ["author_id" => intval($matches[1])], "set_author_about");
                    $keyboard = [
                        "inline_keyboard" => [
                            [
                                ["text" => $translator->translate("go_back"), "callback_data" => "author_" . $matches[1]]
                            ]
                        ]
                    ];
                    $this->editMediaMessage($chatId, $currentPanel, ($author["biography"] ? $visualsLinks[5] : $visualsLinks[4]), ($author["biography"] ? $translator->translate("pending_bio_change") : $translator->translate("pending_bio_add")), $keyboard);
                } else {
                    $this->sendMessage($chatId, $translator->translate("no_permission_message"));
                }
            } elseif (preg_match("/^add_author_link_(\d+)$/", $payload, $matches)) {
                if ($dbManager->hasHigherRole($userId, "moderator")) {
                    $dbManager->setState($userId, ["author_id" => intval($matches[1])], "add_author_link");
                    $keyboard = [
                        "inline_keyboard" => [
                            [
                                ["text" => $translator->translate("go_back"), "callback_data" => "author_" . $matches[1]]
                            ]
                        ]
                    ];
                    $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[1], $translator->translate("pending_link_change"), $keyboard);
                } else {
                    $this->sendMessage($chatId, $translator->translate("no_permission_message"));
                }
            } elseif ("list_of_authors" === $payload) {
                if ($dbManager->hasHigherRole($userId, "moderator")) {
                    $dbManager->setCurrentPage($userId, "page_1");
                    $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[9], $translator->translate("list_of_authors_message"), $this->generateAuthorsKeyboard());
                } else {
                    $this->sendMessage($chatId, $translator->translate("no_permission_message"));
                }
            } elseif (preg_match("/^page_(\d+)$/", $payload, $matches)) {
                if ($dbManager->hasHigherRole($userId, "moderator")) {
                    $dbManager->setCurrentPage($userId, $payload);
                    $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[9], $translator->translate("list_of_authors_message"), $this->generateAuthorsKeyboard($matches[1]));
                } else {
                    $this->sendMessage($chatId, $translator->translate("no_permission_message"));
                }
            } elseif (preg_match("/^author_(\d+)$/", $payload, $matches)) {
                if ($dbManager->hasHigherRole($userId, "moderator")) {
                    $author = $dbManager->getAuthorById(intval($matches[1]));
                    if (false !== $author) {
                        $authorStatus = $dbManager->isPrivate(intval($matches[1]));
                        $keyboard = [
                            "inline_keyboard" => [
                                [
                                    ["text" => $translator->translate("change_name"), "callback_data" => "change_name_" . $matches[1]],
                                    ["text" => ($authorStatus ? $translator->translate("make_public") : $translator->translate("make_private")), "callback_data" => "set_private_" . $matches[1]]
                                ],
                                [
                                    ["text" => ($author["biography"] ? $translator->translate("change_bio") : $translator->translate("add_bio")), "callback_data" => "set_author_about_" . $matches[1]],
                                    ["text" => ($author["channel_link"] ? $translator->translate("change_link") : $translator->translate("add_link")), "callback_data" => "add_author_link_" . $matches[1]]
                                ],
                                [
                                    ["text" => $translator->translate("delete_this_author"), "callback_data" => "author_to_delete_" . $matches[1]]
                                ],
                                [
                                    ["text" => $translator->translate("go_back"), "callback_data" => $currentPage ?? "control_panel"]
                                ]
                            ]
                        ];
                        $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[11], str_replace(["{author}", "{biography}", "{link}"], [htmlspecialchars($author["name"]), ($author["biography"] ? htmlspecialchars($author["biography"]) : $translator->translate("bio_not_set")), ($author["channel_link"] ? htmlspecialchars($author["channel_link"]) : $translator->translate("link_not_set"))], $translator->translate("author_info_message")), $keyboard);
                        return;
                    }
                    $keyboard = [
                        "inline_keyboard" => [
                           [
                                ["text" => $translator->translate("go_back"), "callback_data" => "control_panel"]
                            ]
                        ]
                    ];
                    $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[1], $translator->translate("author_not_found_message"), $keyboard);
                } else {
                    $this->sendMessage($chatId, $translator->translate("no_permission_message"));
                }
            } elseif (preg_match("/^profile_author_(\d+)$/", $payload, $matches)) {
                $author = $dbManager->getAuthorById(intval($matches[1]));
                if (false !== $author) {
                    $avatar = $this->createAvatar($author["name"], $author["channel_link"]);
                    $this->pictureReply($chatId, str_replace(["{author}", "{biography}", "{link}"], [htmlspecialchars($author["name"]), ($author["biography"] ? htmlspecialchars($author["biography"]) : $translator->translate("bio_not_set")), ($author["channel_link"] ? htmlspecialchars($author["channel_link"]) : $translator->translate("link_not_set"))], $translator->translate("author_info_message")), $avatar);
                    unlink($avatar);
                    return;
                }
                $this->sendMessage($chatId, $translator->translate("author_not_found_message"));
            } elseif ("access_control" === $payload) {
                if ($dbManager->hasHigherRole($userId, "admin")) {
                    $dbManager->clearState($userId);
                    if (! is_null($currentPage)) {
                        $dbManager->resetCurrentPage($userId);
                    }
                    $keyboard = [
                       "inline_keyboard" => [
                            [
                                ["text" => $translator->translate("create_role"), "callback_data" => "create_role"],
                                ["text" => $translator->translate("delete_role"), "callback_data" => "delete_role"]
                            ],
                            [
                                ["text" => $translator->translate("view_roles"), "callback_data" => "view_roles"],
                                ["text" => $translator->translate("change_priorities"), "callback_data" => "change_priorities"]
                            ],
                            [
                                ["text" => $translator->translate("go_back"), "callback_data" => "control_panel"]
                            ]
                        ]
                    ];
                    $this->editMediaMessage($chatId, $currentPanel, $visualsLinks[1], $translator->translate("access_control_panel_message"), $keyboard);
                } else {
                    $this->sendMessage($chatId, $translator->translate("no_permission_message"));
                }
            } elseif ("change_priorities" === $payload) {
                $this->sendUpdateRolesPriorityPanel($chatId, $userId, $callbackQueryId);
            } elseif (strpos($payload, "select_role:") === 0) {
                list(, $roleName, $roleLevel) = explode(":", $payload);
                $this->toggleRoleSelection($chatId, $userId, $callbackQueryId, $roleName, $roleLevel);
            } elseif (strpos($payload, "noop:") === 0) {
                if ($selectedRole = $dbManager->getState($userId, "selected_role")) {
                    list(, $priority) = explode(":", $payload);
                    $this->updateRolePriorityAndLevel($chatId, $userId, $callbackQueryId, $selectedRole["role_name"], $selectedRole["role_level"], null, "secondary", (int) $priority);
                }
            }
        }
    }

    private function extractMessageData($data)
    {
        $callbackQuery = $data["callback_query"] ?? null;
        return [
            "message" => $callbackQuery["message"]["text"] ?? $data["message"]["text"] ?? null,
            "messageId" => $callbackQuery["message"]["message_id"] ?? $data["message"]["message_id"] ?? null,
            "chatType" => $callbackQuery["message"]["chat"]["type"] ?? $data["message"]["chat"]["type"] ?? null,
            "chatId" => $callbackQuery["message"]["chat"]["id"] ?? $data["message"]["chat"]["id"] ?? null,
            "userId" => $callbackQuery["message"]["from"]["id"] ?? $data["message"]["from"]["id"] ?? null,
            "language" => $callbackQuery["from"]["language_code"] ?? $data["message"]["from"]["language_code"] ?? null,
            "payload" => $callbackQuery["data"] ?? null,
            "callbackQueryId" => $callbackQuery["id"] ?? null,
            "replyMessageId" => $data["message"]["reply_to_message"]["message_id"] ?? null,
            "replyAuthor" => $data["message"]["reply_to_message"]["from"]["id"] ?? null,
            "firstName" => $data["message"]["from"]["first_name"] ?? null
        ];
    }

    protected function createAvatar($authorName, $authorLink, $authorImage = "path/to/author_image.jpg")
    {
        $template = imagecreatefrompng("path/to/template.png");
        $authorImage = imagecreatefrompng($authorImage);
        $stamp = imagecreatefrompng("path/to/stamp.png");

        $authorWidth = imagesx($authorImage);
        $authorHeight = imagesy($authorImage);

        $cropWidth = min($authorWidth, $authorHeight);
        $cropHeight = min($authorWidth, $authorHeight);

        $croppedAuthorImage = imagecreatetruecolor($cropWidth, $cropHeight);
        imagecopyresized($croppedAuthorImage, $authorImage, 0, 0, ($authorWidth - $cropWidth) / 2, ($authorHeight - $cropHeight) / 2, $cropWidth, $cropHeight, $cropWidth, $cropHeight);
        imagecopyresampled($template, $croppedAuthorImage, 53, 80, 0, 0, 240, 260, $cropWidth, $cropHeight);

        imagecopy($template, $stamp, 175, 125, 0, 0, 300, 300);

        $textColor = imagecolorallocate($template, 0, 0, 0);

        $font = "path/to/font.ttf";

        $textWidth = imagettfbbox(20, -1, $font, $authorName);
        $textWidth = $textWidth[2] - $textWidth[0];

        $x = (imagesx($template) - $textWidth) / 2;
        $y = 440;

        imagettftext($template, 20, -1, $x, $y, $textColor, $font, $authorName);

        $textWidth = imagettfbbox(20, -1, $font, $authorLink);
        $textWidth = $textWidth[2] - $textWidth[0];

        $x = (imagesx($template) - $textWidth) / 2;
        $y = 575;

        imagettftext($template, 20, -1, $x, $y, $textColor, $font, $authorLink);

        $tempFile = tempnam(sys_get_temp_dir(), "img");
        imagepng($template, $tempFile);

        return curl_file_create($tempFile);
    }

    public function generateAuthorsKeyboard($pageNumber = 1, $buttonsPerPage = 3, $authorsPerRow = 1, $prefix = "author_", $pagePrefix = "page_", $authors = null) : array
    {
        $initialAuthors = $authors;
        $authors = $authors ?? $dbManager->getAllAuthors();
        $totalButtons = count($authors);
        $totalPages = (int) ceil($totalButtons / $buttonsPerPage);
        $pageNumber = max(1, min($pageNumber, $totalPages));
        $offset = ($pageNumber - 1) * $buttonsPerPage;
        $keyboard = ["inline_keyboard" => []];
        $currentRow = [];
        $rowCount = 0;
        $slicedAuthors = array_slice($authors, $offset, $buttonsPerPage);

        foreach ($slicedAuthors as $author) {
            $currentRow[] = ["text" => $author["name"], "callback_data" => $prefix . $author["id"]];
            $rowCount++;

            if ($rowCount === $authorsPerRow) {
                $keyboard["inline_keyboard"][] = $currentRow;
                $currentRow = [];
                $rowCount = 0;
            }
        }

        $translator = $this->container->get('translator');

        if (! empty($currentRow)) {
            $keyboard["inline_keyboard"][] = $currentRow;
        }

        if ($totalPages > 1) {
            $pagination = [];

            if ($pageNumber > 1) {
                $pagination[] = ["text" => $translator->translate("previous_page"), "callback_data" => $pagePrefix . ($pageNumber - 1)];
            }

            if ($pageNumber < $totalPages) {
                $pagination[] = ["text" => $translator->translate("next_page"), "callback_data" => $pagePrefix . ($pageNumber + 1)];
            }

            $keyboard["inline_keyboard"][] = $pagination;
        }

        if (is_null($initialAuthors)) {
            $keyboard["inline_keyboard"][] = [["text" => $translator->translate("go_back"), "callback_data" => "control_panel"]];
        }

        return $keyboard;
    }

    public function sendUpdateRolesPriorityPanel(int $chatId, int $userId, $callbackQueryId) : void
    {
        $dbManager = $this->container->get('dbManager');
        $translator = $this->container->get('translator');
        $visualsLinks = $this->container->get('visualsLinks');

        if ($dbManager->hasHigherRole($userId, "admin")) {
            $result = $dbManager->db->query("SELECT * FROM roles ORDER BY priority DESC, level ASC");
            $roles = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $roles[] = [
                    "role_name" => $row["role_name"],
                    "priority" => $row["priority"],
                    "level" => $row["level"]
                ];
            }

            $selectedRole = $dbManager->getState($userId, "selected_role");

            $rolesByPriority = [];
            foreach ($roles as $role) {
                $rolesByPriority[$role["priority"]][$role["level"]][] = $role;
            }

            $keyboard = ["inline_keyboard" => []];

            foreach ($rolesByPriority as $priority => $levels) {
                $keyboardRow = [];

                if (isset($levels["primary"][0])) {
                    $primaryRole = $levels["primary"][0];
                    $primaryButtonText = ($selectedRole && $selectedRole["role_name"] === $primaryRole["role_name"]) ? "✔ {$primaryRole["role_name"]} ({$primaryRole["priority"]})" : "{$primaryRole["role_name"]} ({$primaryRole["priority"]})";
                    $keyboardRow[] = [
                        "text" => $primaryButtonText,
                        "callback_data" => "select_role:{$primaryRole["role_name"]}:primary"
                    ];
                } else {
                    $keyboardRow[] = ["text" => " ", "callback_data" => "noop"];
                }

                if (isset($levels["secondary"][0])) {
                    $secondaryRole = $levels["secondary"][0];
                    $secondaryButtonText = ($selectedRole && $selectedRole["role_name"] === $secondaryRole["role_name"]) ? "✔ {$secondaryRole["role_name"]} ({$secondaryRole["priority"]})" : "{$secondaryRole["role_name"]} ({$secondaryRole["priority"]})";
                    $keyboardRow[] = [
                        "text" => $secondaryButtonText,
                        "callback_data" => "select_role:{$secondaryRole["role_name"]}:secondary"
                    ];
                } else {
                    $keyboardRow[] = ["text" => " ", "callback_data" => "noop:{$primaryRole["priority"]}"];
                }

                $keyboard["inline_keyboard"][] = $keyboardRow;
            }

            $keyboard["inline_keyboard"][] = [
                ["text" => $translator->translate("go_back"), "callback_data" => "access_control"]
            ];

            $this->editMediaMessage($chatId, $dbManager->getCurrentPanel($userId), $visualsLinks[1], "Ви можете змінити пріоритети ролей:", $keyboard);
        } else {
            $this->sendMessage($chatId, $translator->translate("no_permission_message"));
        }
    }

    public function toggleRoleSelection(int $chatId, int $userId, $callbackQueryId, string $roleName, string $roleLevel) : void
    {
        $selectedRole = $dbManager->getState($userId, "selected_role");
        if ($selectedRole && $selectedRole["role_name"] === $roleName) {
            $dbManager->clearState($userId, "selected_role");
            $this->callbackAnswer($callbackQueryId, "Ви зняли виділення з ролі «" . $roleName . "».");
        } elseif ($selectedRole) {
            $this->updateRolePriorityAndLevel($chatId, $userId, $callbackQueryId, $selectedRole["role_name"], $selectedRole["role_level"], $roleName, $roleLevel);
        } else {
            $dbManager->setState($userId, ["role_name" => $roleName, "role_level" => $roleLevel], "selected_role");
            $this->callbackAnswer($callbackQueryId, "Ви вибрали роль «" . $roleName . "» для зміни пріоритету.");
        }
        $this->sendUpdateRolesPriorityPanel($chatId, $userId, $callbackQueryId);
    }

    public function updateRolePriorityAndLevel(int $chatId, int $userId, $callbackQueryId, string $selectedRoleName, string $selectedRoleLevel, string|null $targetRoleName, string $targetRoleLevel, ?int $newPriority) : void
    {
        $primaryRolesPriorities = $dbManager->getRolesPriorities("primary");

        $dbManager->recalculatePriorities($primaryRolesPriorities);

        $selectedRolePriority = $dbManager->getRolePriority($selectedRoleName);
        $secondaryCount = $dbManager->getRolesCount("secondary");

        if ($this->cannotMoveToSecondary($selectedRoleLevel, $targetRoleLevel, $secondaryCount, count($primaryRolesPriorities))) {
            $this->callbackAnswer($callbackQueryId, "Не можна переміщувати більше ролей до другорядних.");
            return;
        }

        if ($targetRoleName) {
            $targetRole = $dbManager->getRoleByName($targetRoleName);

            if (is_null($targetRole)) {
                $this->callbackAnswer($callbackQueryId, "Цільова роль не знайдена.");
                return;
            }

            $this->handleRolePriorityUpdate($selectedRoleLevel, $targetRoleLevel, $selectedRolePriority, $targetRole, $selectedRoleName);
        } else {
            $this->handleRoleLevelChange($selectedRoleLevel, $targetRoleLevel, $selectedRoleName, $newPriority);
        }

        $dbManager->clearState($userId, "selected_role");
        $this->callbackAnswer($callbackQueryId, "Роль «" . $selectedRoleName . "» оновлена.");
        $this->sendUpdateRolesPriorityPanel($chatId, $userId, $callbackQueryId);
    }

    private function cannotMoveToSecondary(string $selectedRoleLevel, string $targetRoleLevel, int $secondaryCount, int $primaryCount) : bool
    {
        return $selectedRoleLevel === "primary" && $targetRoleLevel === "secondary" && $secondaryCount >= $primaryCount;
    }

    private function handleRolePriorityUpdate(string $selectedRoleLevel, string $targetRoleLevel, int $selectedRolePriority, array $targetRole, string $selectedRoleName) : void
    {
        $targetPriority = $targetRole["priority"];

        if ($selectedRoleLevel === $targetRoleLevel) {
            $dbManager->updateRolePriorities($selectedRolePriority, $targetPriority, $targetRoleLevel);
            $dbManager->updateRolePriority($selectedRoleName, $targetPriority);
        } else {
            $dbManager->decrementPrioritiesAbove($selectedRoleLevel, $selectedRolePriority);
            $newPriority = $dbManager->getMaxPriority($targetRoleLevel) + 1;
            $dbManager->updateRoleLevelAndPriority($selectedRoleName, $targetRoleLevel, $newPriority);
        }
    }

    private function handleRoleLevelChange(string $selectedRoleLevel, string $targetRoleLevel, string $selectedRoleName, ?int $newPriority) : void
    {
        $dbManager = $this->container->get('dbManager');

        if ($selectedRoleLevel === "primary" && $selectedRoleLevel !== $targetRoleLevel) {
            $dbManager->db->exec("BEGIN TRANSACTION");

            try {
                // Зберігаємо первинні ролі та їх пріоритети, виключаючи вибрану роль
                $primaryRolesPriorities = [];
                $stmt = $dbManager->db->prepare("SELECT role_name, priority FROM roles WHERE level = 'primary' AND role_name != :selected_role_name ORDER BY priority ASC");
                $stmt->bindValue(":selected_role_name", $selectedRoleName, SQLITE3_TEXT);
                $result = $stmt->execute();

                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $primaryRolesPriorities[$row["priority"]] = $row["role_name"];
                }

                // Перевизначаємо пріоритети первинних ролей та зберігаємо відповідність старих пріоритетів новим
                $primaryCount = count($primaryRolesPriorities);
                $step = 100 / ($primaryCount - 1);
                $oldToNewPriorities = [];

                foreach ($primaryRolesPriorities as $oldPriority => $roleName) {
                    $newPriority = round(array_search($oldPriority, array_keys($primaryRolesPriorities)) * $step);
                    $oldToNewPriorities[$oldPriority] = $newPriority;
                    $stmt = $dbManager->db->prepare("UPDATE roles SET priority = :priority WHERE role_name = :role_name");
                    $stmt->bindValue(":priority", $newPriority, SQLITE3_INTEGER);
                    $stmt->bindValue(":role_name", $roleName, SQLITE3_TEXT);
                    $this->executeWithRetry($stmt);
                }

                // Оновлюємо пріоритети другорядних ролей, враховуючи відповідність старих та нових пріоритетів
                $stmt = $dbManager->db->query("SELECT role_name, priority FROM roles WHERE level = 'secondary' ORDER BY priority ASC");
                while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) {
                    $oldPriority = $row["priority"];
                    $newPriority = $oldToNewPriorities[$oldPriority] ?? $oldPriority;
                    $roleName = $row["role_name"];
                    $stmtUpdate = $dbManager->db->prepare("UPDATE roles SET priority = :priority WHERE role_name = :role_name");
                    $stmtUpdate->bindValue(":priority", $newPriority, SQLITE3_INTEGER);
                    $stmtUpdate->bindValue(":role_name", $roleName, SQLITE3_TEXT);
                    $this->executeWithRetry($stmtUpdate);
                }

                // Додаємо вибрану роль до другорядних ролей з новим пріоритетом
                $newPriority = $newPriority ?? (max($oldToNewPriorities) + 1);
                $stmt = $dbManager->db->prepare("UPDATE roles SET level = :role_level, priority = :priority WHERE role_name = :role_name");
                $stmt->bindValue(":role_name", $selectedRoleName, SQLITE3_TEXT);
                $stmt->bindValue(":role_level", $targetRoleLevel, SQLITE3_TEXT);
                $stmt->bindValue(":priority", $newPriority, SQLITE3_INTEGER);
                $this->executeWithRetry($stmt);

                $dbManager->db->exec("COMMIT");
            } catch (Exception $e) {
                $dbManager->db->exec("ROLLBACK");
                throw $e;
            }
        } else {
            $dbManager->updateRoleLevelAndPriority($selectedRoleName, $targetRoleLevel, $newPriority);
        }
    }

    private function executeWithRetry($stmt, $retries = 5, $delay = 100)
    {
        for ($i = 0; $i < $retries; $i++) {
            try {
                $stmt->execute();
                return;
            } catch (Exception $e) {
                if ($i < $retries - 1 && $e->getMessage() == "database is locked") {
                    usleep($delay * 1000);
                    continue;
                }
                throw $e;
            }
        }
    }
}
