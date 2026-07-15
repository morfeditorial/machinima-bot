<div align="center">

<img src="assets/images/morf-logo.svg" alt="MORF" width="320" />

# machinima-bot

*A Telegram bot written in PHP for managing machinimators.*

[Features](#features) · [Requirements](#requirements) · [Installation](#installation) · [Usage](#usage) · [Contributing](#contributing)

---

</div>

**MachinimaBot** is a Telegram bot written in PHP. It is designed to interact with users on Telegram, providing various functionalities based on the commands it receives.

![Bot Control Panel Appearance](assets/images/IMG_20250213_144644_598.png)
> *Screenshot of the admin panel showing the management capabilities of the Telegram bot.*

## Features

- **Real-Time Telegram Interaction**: Responds to user messages and commands in real time.
- **Environment-Based Configuration**: Easy setup using environment variables and a `.env` file.
- **Flexible Update Handling**: Supports both Webhook mode and continuous polling for updates.
- **Machinimator Management Commands**: Provides commands and tools for managing machinimators.

## Requirements

- PHP 7.4 or higher
- Composer for dependency management

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/ChernegaSergiy/machinima-bot.git
   cd machinima-bot
   ```

3. Install dependencies using Composer:
   ```bash
   composer install
   ```

4. Copy the `.env.example` file to `.env` and set your Telegram bot token:
   ```bash
   cp .env.example .env
   ```
   Edit the `.env` file to include your `BOT_TOKEN`.

## Usage

Run the bot:

```bash
php bot.php
```

If you want the bot to work on Webhook, uncomment the Webhook section in `bot.php`:

```php
// $update = json_decode(file_get_contents("php://input"), true);
// if ($update) {
//     $bot->handleUpdate($update);
// }
```

## System Initialization

The following output shows the initialization process of the Morf Editorial System, which serves as the backbone of MachinimaBot. It verifies the configuration, environment variables, and initializes the bot core before entering the main loop.

```text
    _______   _______    _______   _______
   /       \\/       \\//       \//       \
  /        //        ///        //      __/
 /         /         /        _/        _/
 \__/__/__/\________/\____/___/\_______/

 Morf Editorial System v1.0
 Copyright (c) 2023 CSSM Group
 https://cssm.pp.ua/

Initializing Morf Editorial System...
Loading configuration...
Configuration loaded successfully.
Checking environment variables...
Environment variables verified.
Initializing bot core...
Bot core initialized successfully.
Morf Editorial System is now online.
Entering main loop...
```

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
