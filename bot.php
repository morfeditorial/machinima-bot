#!/usr/bin/php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use morfeditorial\MyBot;

class BotManager
{
    private const PID_FILE = __DIR__ . '/bot.pid';

    private const LOG_DIR = __DIR__ . '/logs';

    private const START_TIME_FILE = __DIR__ . '/bot_start_time.txt';

    private const ENV_FILE = __DIR__ . '/.env';

    private $bot;

    private $isDaemon;

    private $statsManager;

    public function __construct($isDaemon = false)
    {
        $this->isDaemon = $isDaemon;
    }

    private function initializeSystem()
    {
        if (! is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0777, true);
        }
        if (! $this->isDaemon) {
            $this->clearScreen();
            $this->printLogo();
            $this->printSlowly('Initializing Morf Editorial System...', 50000);
            sleep(1);
        }

        $this->loadConfiguration();
        $this->initializeBot();
        $this->statsManager = new StatisticsManager(self::LOG_DIR, $this->bot);
    }

    private function loadConfiguration()
    {
        if (! file_exists(self::ENV_FILE)) {
            $this->createEnvFile();
        }

        if (! $this->isDaemon) {
            $this->printSlowly('Loading configuration...', 50000);
        }

        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        if (! $this->isDaemon) {
            $this->printSuccess('Configuration loaded successfully.');
            sleep(1);
            $this->printSlowly('Checking environment variables...', 50000);
        }

        if (! isset($_ENV['BOT_TOKEN'])) {
            throw new \RuntimeException('BOT_TOKEN not set in the .env file.');
        }

        if (! $this->isDaemon) {
            $this->printSuccess('Environment variables verified.');
            sleep(1);
        }
    }

    private function createEnvFile()
    {
        $this->printWarning('.env file not found. Please enter your bot API token:');
        $apiToken = trim(fgets(STDIN));
        file_put_contents(self::ENV_FILE, "BOT_TOKEN={$apiToken}\n");
        $this->printSuccess('API token saved to .env file.');
    }

    private function initializeBot()
    {
        if (! $this->isDaemon) {
            $this->printSlowly('Initializing bot core...', 50000);
        }

        try {
            $this->bot = new MyBot($_ENV['BOT_TOKEN']);
            $this->recordStartTime();
            if (! $this->isDaemon) {
                $this->printSuccess('Bot core initialized successfully.');
            }
            sleep(1);
        } catch (\Exception $e) {
            $error = 'Failed to initialize bot core: ' . $e->getMessage();
            $this->logError($error);
            throw new \RuntimeException($error);
        }
    }

    public function run()
    {
        $this->initializeSystem();
        $this->printSuccess('Morf Editorial System is now online.');
        $this->printSlowly('Entering main loop...', 50000);

        $lastHealthCheck = time();
        $healthCheckInterval = 300;

        while (true) {
            try {
                $currentTime = time();
                if ($currentTime - $lastHealthCheck >= $healthCheckInterval) {
                    $this->performHealthCheck();
                    $lastHealthCheck = $currentTime;
                }
                $this->statsManager->checkStatsTime();
                $updates = $this->bot->getUpdates();
                foreach ($updates as $update) {
                    $this->logUpdateToTerminal($update);
                    $this->statsManager->updateStats($update);
                    $this->bot->handleUpdate($update);
                }
                sleep(1);
            } catch (\Exception $e) {
                $this->handleException($e);
            }
        }
    }

    private function logUpdateToTerminal($update)
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "\n[$timestamp] Received new update:\n";

        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $userId = $message['from']['id'];
            $text = $message['text'] ?? '(no text)';
            $firstName = $message['from']['first_name'] ?? 'Unknown';

            echo "Type: Message\n";
            echo "From: $firstName (ID: $userId)\n";
            echo "Chat ID: $chatId\n";
            echo 'Text: ' . substr($text, 0, 50) . (strlen($text) > 50 ? '...' : '') . "\n";
        } elseif (isset($update['callback_query'])) {
            $callbackQuery = $update['callback_query'];
            $userId = $callbackQuery['from']['id'];
            $data = $callbackQuery['data'];
            $firstName = $callbackQuery['from']['first_name'] ?? 'Unknown';

            echo "Type: Callback Query\n";
            echo "From: $firstName (ID: $userId)\n";
            echo "Data: $data\n";
        } elseif (isset($update['edited_message'])) {
            $editedMessage = $update['edited_message'];
            $chatId = $editedMessage['chat']['id'];
            $userId = $editedMessage['from']['id'];
            $text = $editedMessage['text'] ?? '(no text)';
            $firstName = $editedMessage['from']['first_name'] ?? 'Unknown';

            echo "Type: Edited Message\n";
            echo "From: $firstName (ID: $userId)\n";
            echo "Chat ID: $chatId\n";
            echo 'New text: ' . substr($text, 0, 50) . (strlen($text) > 50 ? '...' : '') . "\n";
        }

        echo "------------------------\n";
    }

    private function performHealthCheck()
    {
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $uptime = time() - (int) file_get_contents(self::START_TIME_FILE);

        $this->printSuccess("\nHealth Check Report:");
        echo 'Memory Usage: ' . round($memoryUsage, 2) . " MB\n";
        echo 'Uptime: ' . $this->formatUptime($uptime) . "\n";

        if ($memoryUsage > 100) {
            $this->performMemoryCleanup();
        }
    }

    private function performMemoryCleanup()
    {
        $this->printWarning('High memory usage detected. Performing cleanup...');
        gc_collect_cycles();
        $newMemoryUsage = memory_get_usage(true) / 1024 / 1024;
        $this->printSuccess('Cleanup completed. New memory usage: ' . round($newMemoryUsage, 2) . ' MB');
    }

    private function handleException(\Exception $e)
    {
        $this->printError('An exception occurred in the main loop.');
        $this->printError('Error message: ' . $e->getMessage());
        $this->printError('Attempting to recover...');
        $this->logError($e->getMessage());
        sleep(5);
    }

    public function daemonize()
    {
        if (file_exists(self::PID_FILE)) {
            $oldPid = file_get_contents(self::PID_FILE);
            if (posix_kill($oldPid, 0)) {
                $this->printWarning("Found running bot instance (PID: $oldPid).");
                $this->printSlowly('Attempting to terminate old process...', 50000);

                if (posix_kill($oldPid, SIGTERM)) {
                    sleep(2);
                    if (posix_kill($oldPid, 0)) {
                        $this->printError('Failed to terminate old process.');
                        exit(3);
                    }
                    $this->printSuccess('Old process terminated successfully.');
                    unlink(self::PID_FILE);
                    if (file_exists(self::START_TIME_FILE)) {
                        unlink(self::START_TIME_FILE);
                    }
                    sleep(1);
                } else {
                    $this->printError('Failed to terminate old process.');
                    exit(4);
                }
            } else {
                unlink(self::PID_FILE);
                if (file_exists(self::START_TIME_FILE)) {
                    unlink(self::START_TIME_FILE);
                }
            }
        }

        $pid = pcntl_fork();
        if (-1 == $pid) {
            exit("Could not fork.\n");
        } elseif ($pid) {
            file_put_contents(self::PID_FILE, $pid);
            $this->printSuccess('Bot started as daemon with PID: ' . $pid);
            exit(0);
        }

        posix_setsid();
        chdir(__DIR__);
        umask(0);

        if (! is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0777, true);
        }

        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);

        $stdIn = fopen('/dev/null', 'r');
        $stdOut = fopen(self::LOG_DIR . '/bot.log', 'a+');
        $stdErr = fopen(self::LOG_DIR . '/error.log', 'a+');

        stream_set_blocking($stdIn, 0);
        stream_set_blocking($stdOut, 0);
        stream_set_blocking($stdErr, 0);

        $this->run();
    }

    public function checkStatus()
    {
        if (! file_exists(self::PID_FILE)) {
            $this->printError('Bot is not running.');

            return false;
        }

        $pid = file_get_contents(self::PID_FILE);
        if (file_exists("/proc/$pid")) {
            $this->printSuccess("Bot is running (PID: $pid).");
            $this->printProcessInfo($pid);

            return true;
        } else {
            $this->printError('Bot process not found (stale PID file).');
            unlink(self::PID_FILE);

            return false;
        }
    }

    public function stopDaemon()
    {
        if (! file_exists(self::PID_FILE)) {
            $this->printError('Bot is not running.');

            return false;
        }

        $pid = file_get_contents(self::PID_FILE);
        if (posix_kill($pid, SIGTERM)) {
            unlink(self::PID_FILE);
            $this->printSuccess('Bot stopped successfully.');

            return true;
        } else {
            $this->printError('Failed to stop bot.');

            return false;
        }
    }

    private function clearScreen()
    {
        echo "\033c";
    }

    private function printLogo()
    {
        $logo = "
    _______   _______    _______   _______
   /       \\\/       \\\//       \//       \
  /        //        ///        //      __/
 /         /         /        _/        _/
 \__/__/__/\________/\____/___/\_______/

 Morf Editorial System v1.0
 Copyright (c) 2023 CSSM Group
 https://cssm.pp.ua/

";
        echo $logo;
    }

    private function printSlowly($text, $delay = 100000)
    {
        foreach (str_split($text) as $char) {
            echo $char;
            usleep($delay);
        }
        echo "\n";
    }

    private function printWarning($message)
    {
        echo "\033[1;33m" . $message . "\033[0m\n";
        $this->logMessage($message, 'WARNING');
    }

    private function printSuccess($message)
    {
        echo "\033[0;32m" . $message . "\033[0m\n";
        $this->logMessage($message, 'SUCCESS');
    }

    private function printError($message)
    {
        echo "\033[0;31m" . $message . "\033[0m\n";
        $this->logMessage($message, 'ERROR');
    }

    private function logMessage($message, $level)
    {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents(self::LOG_DIR . '/bot.log', "[$timestamp] [$level] $message\n", FILE_APPEND);
    }

    private function recordStartTime()
    {
        $startTime = time();
        file_put_contents(self::START_TIME_FILE, $startTime);
    }

    private function formatUptime($uptime)
    {
        $hours = floor($uptime / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        $seconds = $uptime % 60;

        return "{$hours}h {$minutes}m {$seconds}s";
    }

    private function printProcessInfo($pid)
    {
        $memory = file_get_contents("/proc/$pid/status");
        if ($memory && preg_match('/VmRSS:\s+(\d+)/', $memory, $matches)) {
            $memoryKb = (int) $matches[1];
            $memoryMb = round($memoryKb / 1024, 2);
            echo "Memory usage: {$memoryMb} MB\n";
        }
    }
}

class StatisticsManager
{
    private const STATS_TIME = '23:59';

    private $logDir;

    private $bot;

    private $dailyStats;

    private $lastStatsReset;

    public function __construct(string $logDir, MyBot $bot)
    {
        $this->logDir = $logDir;
        $this->bot = $bot;
        $this->initializeStats();
    }

    private function initializeStats() : void
    {
        $this->dailyStats = [
            'messages' => 0,
            'callbacks' => 0,
            'edited_messages' => 0,
            'unique_users' => [],
        ];
        $this->loadStats();
        $this->lastStatsReset = strtotime(date('Y-m-d'));
    }

    private function getCurrentStatsFile() : string
    {
        $date = date('Y-m-d');

        return $this->logDir . "/stats_{$date}.json";
    }

    private function loadStats() : void
    {
        $statsFile = $this->getCurrentStatsFile();
        if (file_exists($statsFile)) {
            $stats = json_decode(file_get_contents($statsFile), true);
            if ($stats) {
                $this->dailyStats = $stats;
            }
        }
    }

    private function saveStats() : void
    {
        $statsFile = $this->getCurrentStatsFile();
        file_put_contents($statsFile, json_encode($this->dailyStats));
    }

    public function updateStats(array $update) : void
    {
        $userId = $this->extractUserId($update);
        $this->incrementStats($update);

        if ($userId && ! in_array($userId, $this->dailyStats['unique_users'])) {
            $this->dailyStats['unique_users'][] = $userId;
        }

        $this->saveStats();
    }

    private function extractUserId(array $update) : ?int
    {
        if (isset($update['message'])) {
            return $update['message']['from']['id'];
        } elseif (isset($update['callback_query'])) {
            return $update['callback_query']['from']['id'];
        } elseif (isset($update['edited_message'])) {
            return $update['edited_message']['from']['id'];
        }

        return null;
    }

    private function incrementStats(array $update) : void
    {
        if (isset($update['message'])) {
            $this->dailyStats['messages']++;
        } elseif (isset($update['callback_query'])) {
            $this->dailyStats['callbacks']++;
        } elseif (isset($update['edited_message'])) {
            $this->dailyStats['edited_messages']++;
        }
    }

    public function checkStatsTime() : void
    {
        $currentTime = date('H:i');
        $currentDate = strtotime(date('Y-m-d'));
        $this->logMessage("Checking stats time. Current time: $currentTime, Stats time: " . self::STATS_TIME, 'DEBUG');

        if (strtotime(self::STATS_TIME) === strtotime($currentTime) && $currentDate > $this->lastStatsReset) {
            $this->logMessage('Stats time condition met. Sending daily stats.', 'DEBUG');
            $this->sendDailyStats();
            $this->resetStats();
            $this->lastStatsReset = $currentDate;
        }
    }

    private function sendDailyStats() : void
    {
        $statsMessage = $this->formatStatsMessage();

        if (isset($_ENV['ADMIN_ID'])) {
            $this->bot->sendMessage($_ENV['ADMIN_ID'], $statsMessage);
        } else {
            this->logMessage('ADMIN_ID not set. Unable to send stats message.', 'WARNING');
        }

        $this->logMessage($statsMessage, 'STATS');
    }

    private function formatStatsMessage() : string
    {
        $date = date('Y-m-d');

        return "📊 Daily Statistics ($date):\n\n"
            . "📨 Messages: {$this->dailyStats['messages']}\n"
            . "🔄 Callbacks: {$this->dailyStats['callbacks']}\n"
            . "✏️ Edited messages: {$this->dailyStats['edited_messages']}\n"
            . '👥 Unique users: ' . count($this->dailyStats['unique_users']);
    }

    private function resetStats() : void
    {
        $this->dailyStats = [
            'messages' => 0,
            'callbacks' => 0,
            'edited_messages' => 0,
            'unique_users' => [],
        ];
        $this->saveStats();
    }

    private function logMessage(string $message, string $level) : void
    {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents(
            $this->logDir . '/bot.log',
            "[$timestamp] [$level] $message\n",
            FILE_APPEND
        );
    }

    public function getDailyStats() : array
    {
        return $this->dailyStats;
    }
}

function showHelp()
{
    echo "Usage: php bot.php [command]\n\n";
    echo "Commands:\n";
    echo "  --daemon     Start bot as daemon\n";
    echo "  --status     Check bot status\n";
    echo "  --stop       Stop bot daemon\n";
    echo "  --help       Show this help message\n";
    echo "\nWithout arguments, bot starts in interactive mode\n";
}

$options = getopt('', ['daemon', 'status', 'stop', 'help']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

$botManager = new BotManager(isset($options['daemon']));

if (isset($options['status'])) {
    $botManager->checkStatus();
    exit(0);
}

if (isset($options['stop'])) {
    $botManager->stopDaemon();
    exit(0);
}

if (isset($options['daemon'])) {
    if (! function_exists('pcntl_fork')) {
        exit("PCNTL extension not installed. Daemon mode unavailable.\n");
    }
    $botManager->daemonize();
} else {
    $botManager->run();
}
