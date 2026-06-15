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

class DependencyContainer
{
    private array $services = [];

    public function __construct($translations, $userLocale)
    {
        $this->services['translator'] = new Translator($translations, $userLocale);
    }

    public function get($service)
    {
        return $this->services[$service] ?? null;
    }

    public function set($service, $object)
    {
        $this->services[$service] = $object;
    }
}
