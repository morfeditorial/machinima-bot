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

namespace morfeditorial\screens;

use morfeditorial\MyBot;

class ScreenDispatcher
{
    private MyBot $bot;

    // Масив мапінгу: "domain:action" => ScreenClass::class
    private array $routes = [
        // Admin
        'admin:panel' => \morfeditorial\screens\Admin\ControlPanelScreen::class,

        // Main
        'main:menu' => \morfeditorial\screens\Main\MainMenuScreen::class,

        // Author
        'author:list' => \morfeditorial\screens\Author\AuthorListScreen::class,
        'author:profile' => \morfeditorial\screens\Author\AuthorProfileScreen::class,
        'author:add' => \morfeditorial\screens\Author\AuthorAddScreen::class,
        'author:change_name' => \morfeditorial\screens\Author\AuthorEditNameScreen::class,
        'author:set_about' => \morfeditorial\screens\Author\AuthorEditBioScreen::class,
        'author:add_link' => \morfeditorial\screens\Author\AuthorEditLinkScreen::class,
        'author:delete_confirm' => \morfeditorial\screens\Author\AuthorDeleteScreen::class,
        'author:set_private' => \morfeditorial\screens\Author\AuthorProfileScreen::class,

        // Role
        'role:control' => \morfeditorial\screens\Role\RoleControlScreen::class,
        'role:create' => \morfeditorial\screens\Role\RoleCreateScreen::class,
        'role:delete' => \morfeditorial\screens\Role\RoleDeleteScreen::class,
        'role:view' => \morfeditorial\screens\Role\RoleViewScreen::class,
        'role:assign' => \morfeditorial\screens\Role\RoleAssignScreen::class,
        'role:remove' => \morfeditorial\screens\Role\RoleRemoveScreen::class,

        // Project
        'project:manage' => \morfeditorial\screens\Project\ProjectManageScreen::class,
        'project:create' => \morfeditorial\screens\Project\ProjectCreateScreen::class,
        'project:set_type' => \morfeditorial\screens\Project\ProjectTypeScreen::class,
        'project:list' => \morfeditorial\screens\Project\ProjectListScreen::class,
        'project:view' => \morfeditorial\screens\Project\ProjectViewScreen::class,
        'project:edit' => \morfeditorial\screens\Project\ProjectEditScreen::class,
        'project:delete' => \morfeditorial\screens\Project\ProjectDeleteScreen::class,

        // Category
        'category:manage' => \morfeditorial\screens\Category\CategoryManageScreen::class,
        'category:create' => \morfeditorial\screens\Category\CategoryCreateScreen::class,
        'category:delete' => \morfeditorial\screens\Category\CategoryDeleteScreen::class,

        // Staff
        'staff:manage' => \morfeditorial\screens\Staff\StaffManageScreen::class,
        'staff:add' => \morfeditorial\screens\Staff\StaffAddScreen::class,
        'staff:remove' => \morfeditorial\screens\Staff\StaffRemoveScreen::class,
    ];

    // Мапінг станів (states) до класів екранів
    private array $stateMap = [
        'awaiting_author_name_creation' => \morfeditorial\screens\Author\AuthorAddScreen::class,
        'change_name' => \morfeditorial\screens\Author\AuthorEditNameScreen::class,
        'set_author_about' => \morfeditorial\screens\Author\AuthorEditBioScreen::class,
        'add_author_link' => \morfeditorial\screens\Author\AuthorEditLinkScreen::class,

        'awaiting_role_creation' => \morfeditorial\screens\Role\RoleCreateScreen::class,
        'awaiting_user_id_for_role' => \morfeditorial\screens\Role\RoleAssignScreen::class,

        'awaiting_project_title' => \morfeditorial\screens\Project\ProjectCreateScreen::class,
        'awaiting_project_description' => \morfeditorial\screens\Project\ProjectCreateScreen::class,
        'awaiting_project_url' => \morfeditorial\screens\Project\ProjectCreateScreen::class,
        'awaiting_project_cover' => \morfeditorial\screens\Project\ProjectCreateScreen::class,
        'editing_project_field' => \morfeditorial\screens\Project\ProjectEditScreen::class,

        'awaiting_category_name' => \morfeditorial\screens\Category\CategoryCreateScreen::class,
        'awaiting_staff_role' => \morfeditorial\screens\Staff\StaffAddScreen::class,
    ];

    public function __construct(MyBot $bot)
    {
        $this->bot = $bot;
    }

    /**
     * Dispatch callback query
     */
    public function dispatchCallback(array $data, string $payload) : bool
    {
        $parts = explode(':', $payload);
        $domain = $parts[0] ?? '';
        $action = $parts[1] ?? '';

        $routeKey = "$domain:$action";

        if (isset($this->routes[$routeKey])) {
            $screenClass = $this->routes[$routeKey];
            /** @var ScreenInterface $screen */
            $screen = new $screenClass($this->bot, $data);

            // Відрізаємо domain та action, залишаємо тільки аргументи
            $args = array_slice($parts, 2);
            $screen->handleCallback($action, $args);
            return true;
        }

        return false;
    }

    /**
     * Dispatch text message based on user's current State
     */
    public function dispatchMessage(array $data, string $text) : bool
    {
        $userId = $data['user_id'];

        $stateKey = $this->bot->getUserStateService()->getState($userId);
        if (is_array($stateKey)) {
            // В старому боті getState міг повертати масив з даними стану
            // Тому нам треба якось ідентифікувати стан. Але в MyBot.php stateKey передавався другим аргументом.
            // Наприклад: $state = $user_state_service->getState($user_id, 'change_name')
        }

        // Для спрощення ми поки будемо перевіряти наявність стану по мапінгу
        foreach ($this->stateMap as $stateName => $screenClass) {
            $stateData = $this->bot->getUserStateService()->getState($userId, $stateName);
            if ($stateData) {
                /** @var ScreenInterface $screen */
                $screen = new $screenClass($this->bot, $data);
                $screen->handleMessage($text);
                return true;
            }
        }

        // Також перевіряємо default state
        $defaultState = $this->bot->getUserStateService()->getState($userId);
        if (is_string($defaultState) && isset($this->stateMap[$defaultState])) {
            $screenClass = $this->stateMap[$defaultState];
            /** @var ScreenInterface $screen */
            $screen = new $screenClass($this->bot, $data);
            $screen->handleMessage($text);
            return true;
        }

        return false;
    }
}
