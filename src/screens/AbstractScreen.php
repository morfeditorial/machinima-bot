<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \/       \//       \//       \
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
namespace morfeditorial\screens;

use morfeditorial\MyBot;

abstract class AbstractScreen implements ScreenInterface
{
    protected MyBot $bot;
    protected int $chatId;
    protected int $userId;
    protected array $data;

    public function __construct(MyBot $bot, array $data)
    {
        $this->bot = $bot;
        $this->data = $data;

        $this->chatId = $data['chat_id'] ?? 0;
        $this->userId = $data['user_id'] ?? 0;
    }

    /**
     * Helper for translations
     */
    protected function translate(string $key, array $params = []) : string
    {
        return $this->bot->getTranslator()->translate($key, $params);
    }

    /**
     * Check if user has role
     */
    protected function isGranted(string $role) : bool
    {
        return $this->bot->isGranted($this->userId, $role);
    }

    /**
     * Parse action and parameters from payload domain:action:arg1:arg2
     * Returns ['action' => 'action', 'params' => ['arg1', 'arg2']]
     */
    protected function parsePayload(string $payload) : array
    {
        $parts = explode(':', $payload);
        $domain = array_shift($parts); // Domain is used to resolve the screen/class, maybe we don't need it here but it's part of the payload
        $action = array_shift($parts) ?? '';

        return [
            'action' => $action,
            'params' => $parts
        ];
    }

    /**
     * Create formatted payload for buttons
     */
    protected function makePayload(string $domain, string $action, ...$params) : string
    {
        $parts = [$domain, $action, ...$params];
        return implode(':', $parts);
    }

    /**
     * Transition to another screen
     */
    protected function transitionTo(string $screenClass, array $params = []) : void
    {
        // 1. Save state in DB
        // $this->bot->getUserStateService()->setCurrentScreen($this->userId, $screenClass, $params);

        // 2. Instantiate and render
        // $screen = new $screenClass($this->bot, $this->data, ...$params);
        // $screen->render();
    }
}
