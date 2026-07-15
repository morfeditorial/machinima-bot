<div align="center">

<img src="assets/images/morf-logo.svg" alt="Morf Editorial" width="320" />

# machinima-bot

*Telegram bot bundle for the Machinima platform.*

[![Latest Stable Version](https://img.shields.io/packagist/v/morfeditorial/machinima-bot.svg?label=Packagist&logo=packagist)](https://packagist.org/packages/morfeditorial/machinima-bot)
[![Total Downloads](https://img.shields.io/packagist/dt/morfeditorial/machinima-bot.svg?label=Downloads&logo=packagist)](https://packagist.org/packages/morfeditorial/machinima-bot)
[![License](https://img.shields.io/packagist/l/morfeditorial/machinima-bot.svg?label=Licence&logo=open-source-initiative)](https://packagist.org/packages/morfeditorial/machinima-bot)

[Features](#features) · [Architecture](#architecture) · [Requirements](#requirements) · [Installation](#installation) · [Usage](#usage) · [Contributing](#contributing)

---

</div>

**machinima-bot** is the official Telegram management bundle for the Machinima platform. It provides role and content management features for the platform's administrators.

![Bot Control Panel Appearance](assets/images/IMG_20250213_144644_598.png)
> *Screenshot of the admin panel showing the management capabilities of the Telegram bot.*

## Features

- **Real-Time Telegram Interaction**: Responds to user messages and commands in real time.
- **Environment-Based Configuration**: Easy setup using environment variables and a `.env` file.
- **Flexible Update Handling**: Supports both Webhook mode and continuous polling for updates.
- **Machinimator Management Commands**: Provides commands and tools for managing machinimators.

## Architecture

This bundle implements a screen-and-command pattern on top of the [`morfeditorial/telegram-bot-bundle`](https://github.com/ChernegaSergiy/telegram-bot-bundle) abstraction layer. It contains **no domain entities or repositories of its own** — all data access is delegated to [`morfeditorial/machinima-core`](https://github.com/ChernegaSergiy/machinima-core) via its shared services (`AuthorRepository`, `UserRepository`, `RoleService`, etc.).

- **Commands** (`src/Commands/`) — Telegram bot commands (`/start`, `/help`, role assignment, admin panel entry point) that extend `BaseMachinimaCommand` and interact with the user through messages.
- **Screens** (`src/Screens/`) — interactive inline-keyboard panels for domain management (authors, projects, categories, roles, staff), organised by domain subdirectory. Each screen extends `BaseMachinimaScreen` and renders a visual panel with callback-driven navigation.
- **Webhook Controller** (`src/Controller/Webhook/`) — receives Telegram updates via `POST /webhook/telegram`, authenticates the calling user through the Security token storage, sets the locale, and delegates to the `UpdateDispatcher` from `telegram-bot-bundle`.
- **Service Providers** (`src/Service/`) — Telegram-specific implementations of core contracts such as `AvatarProviderInterface` and `MediaProviderInterface`, which resolve avatars and media through the Telegram Bot API.

## Requirements

- PHP 8.2 or higher
- Composer for dependency management
- Symfony Framework 7.1+

## Installation

This package is intended to be used as a bundle within the `machinima-app` host application. Require it via Composer:

```bash
composer require morfeditorial/machinima-bot
```

## Usage

The bot runs as part of the host application ecosystem. To run the Telegram bot polling loop locally, use the Symfony console:

```bash
php bin/console morf:bot:poll
```

For production, the bot interacts with Telegram via the configured Webhook route in the host app.

## Contributing

Contributions are welcome and appreciated! Here's how you can contribute:

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

Please make sure to update tests as appropriate and adhere to the existing coding style.

## License

This project is licensed under the CSSM Unlimited License v2.0 (CSSM-ULv2). See the [LICENSE](LICENSE) file for details.
