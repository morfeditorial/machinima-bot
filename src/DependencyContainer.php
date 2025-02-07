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
