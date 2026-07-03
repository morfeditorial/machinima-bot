<?php

declare(strict_types=1);

namespace Morfeditorial\Twig;

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
