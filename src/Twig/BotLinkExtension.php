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

namespace Morfeditorial\MachinimaBotBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BotLinkExtension extends AbstractExtension
{
    public function __construct(
        private string $botUsername,
    ) {}

    public function getFunctions() : array
    {
        return [
            new TwigFunction('get_bot_link', [$this, 'getBotLink']),
            new TwigFunction('has_bot_link', [$this, 'hasBotLink']),
        ];
    }

    public function getBotLink() : string
    {
        return 'https://t.me/'.$this->botUsername;
    }

    public function hasBotLink() : bool
    {
        return '' !== $this->botUsername;
    }
}
