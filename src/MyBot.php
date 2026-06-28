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

use morfeditorial\commands\CommandInterface;
use morfeditorial\security\BotUser;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;

class MyBot extends tgLib
{
    private const DATABASE_FILE = __DIR__ . '/../machinimators.db';

    private const TRANSLATIONS_FILE = __DIR__ . '/../translations.json';

    private CommandFactory $command_factory;

    private \morfeditorial\screens\ScreenDispatcher $screen_dispatcher;

    private ContainerInterface $container;

    public function __construct($token, ContainerInterface $container)
    {
        parent::__construct($token);
        
        $this->container = $container;

        $this->command_factory = new CommandFactory($this, $this->container);
        $this->initializeCommands();

        $this->screen_dispatcher = new \morfeditorial\screens\ScreenDispatcher($this);
    }

    private function initializeCommands() : void
    {
        // Register commands
        $this->command_factory->registerCommand(new \morfeditorial\commands\StartCommand($this)); // start
        $this->command_factory->registerCommand(new \morfeditorial\commands\HelpCommand($this)); // help
        $this->command_factory->registerCommand(new \morfeditorial\commands\UpdateCommand($this)); // update
        $this->command_factory->registerCommand(new \morfeditorial\commands\AdminPanelCommand($this)); // admin_panel
        $this->command_factory->registerCommand(new \morfeditorial\commands\CreateRoleCommand($this)); // create_role
        $this->command_factory->registerCommand(new \morfeditorial\commands\DeleteRoleCommand($this)); // delete_role
        $this->command_factory->registerCommand(new \morfeditorial\commands\AssignInitialAdminCommand($this)); // assign_initial_admin
        $this->command_factory->registerCommand(new \morfeditorial\commands\AssignRoleCommand($this)); // assign_role
        $this->command_factory->registerCommand(new \morfeditorial\commands\RemoveRoleCommand($this)); // remove_role

        $this->command_factory->initializeCommands();
    }

    public function handleUpdate($update) : void
    {
        $message_data = $this->extractMessageData($update);
        $message = $message_data['message'] ?? null;
        $photo = $message_data['photo'] ?? null;

        if (null !== $message_data['user_id']) {
            $this->setupUserToken($message_data['user_id']);
        }

        $this->container->get('bot_translator')->setUserLocale($message_data['language_code'] ?? 'en');

        if (null !== $message || null !== $photo) {
            $this->processMessage($message_data, $message);
        } else {
            $payload = $message_data['payload'] ?? '';

            if (str_contains($payload, ':')) {
                $this->screen_dispatcher->dispatchCallback($message_data, $payload);
            }
        }
    }

    public function getContainer() : \Symfony\Component\DependencyInjection\ContainerInterface
    {
        return $this->container;
    }

    public function getUserService() : \morfeditorial\services\UserService
    {
        return $this->container->get('user_service');
    }

    public function getUserStateService() : \morfeditorial\services\UserStateService
    {
        return $this->container->get('user_state_service');
    }

    private function processMessage(array $message_data, ?string $message) : void
    {
        $command_data = null !== $message ? $this->parseCommand($message) : null;
        if ($command_data) {
            $this->executeCommand($command_data, $message_data);
        } else {
            $text = $message_data['message'] ?? '';
            $this->screen_dispatcher->dispatchMessage($message_data, $text);
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

    private function executeCommand(array $command_data, array $message_data) : void
    {
        $command = $this->command_factory->create($command_data['cmd']);
        if ($command instanceof CommandInterface) {
            $command->execute(
                $message_data['message'],
                $message_data['message_id'],
                $message_data['chat_type'],
                $message_data['chat_id'],
                $message_data['user_id'],
                $message_data['payload'],
                $message_data['reply_message_id'],
                $message_data['reply_author'],
                $message_data['first_name'],
                $this->container->get('user_service')->getCurrentPanel($message_data['user_id']),
                $this->container->get('user_service')->getCurrentPage($message_data['user_id']),
                $command_data['cmd'],
                $command_data['args']
            );
        } else {
            $this->sendMessage($message_data['chat_id'], $this->translate('unknown_command_message'));
        }
    }

    private function extractMessageData($data)
    {
        $callback_query = $data['callback_query'] ?? null;

        return [
            'message' => $callback_query['message']['text'] ?? $data['message']['text'] ?? null,
            'message_id' => $callback_query['message']['message_id'] ?? $data['message']['message_id'] ?? null,
            'chat_type' => $callback_query['message']['chat']['type'] ?? $data['message']['chat']['type'] ?? null,
            'chat_id' => $callback_query['message']['chat']['id'] ?? $data['message']['chat']['id'] ?? null,
            'user_id' => $callback_query['from']['id'] ?? $data['message']['from']['id'] ?? null,
            'language_code' => $callback_query['from']['language_code'] ?? $data['message']['from']['language_code'] ?? null,
            'payload' => $callback_query['data'] ?? null,
            'callback_query_id' => $callback_query['id'] ?? null,
            'reply_message_id' => $data['message']['reply_to_message']['message_id'] ?? null,
            'reply_author' => $data['message']['reply_to_message']['from']['id'] ?? null,
            'first_name' => $data['message']['from']['first_name'] ?? null,
            'photo' => $data['message']['photo'] ?? null,
        ];
    }

    private function setupUserToken(int $user_id) : void
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(\App\Entity\User::class)->find($user_id);
        
        if (!$user) {
            $user = new \Symfony\Component\Security\Core\User\InMemoryUser((string)$user_id, null, ['ROLE_USER']);
            $roles = ['ROLE_USER'];
        } else {
            $roles = $user->getRoles();
        }
        
        $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, 'main', $roles);
        $this->container->get('token_storage')->setToken($token);
    }

    public function isGranted(string $role) : bool
    {
        return $this->container->get('authorization_checker')->isGranted($role);
    }

    public static function createContentWorkflowDefinition() : \Symfony\Component\Workflow\Definition
    {
        $builder = new \Symfony\Component\Workflow\DefinitionBuilder();
        $builder->addPlaces(['draft', 'pending_review', 'published', 'rejected']);

        $builder->addTransition(new \Symfony\Component\Workflow\Transition('submit', 'draft', 'pending_review'));
        $builder->addTransition(new \Symfony\Component\Workflow\Transition('publish', 'pending_review', 'published'));
        $builder->addTransition(new \Symfony\Component\Workflow\Transition('reject', 'pending_review', 'rejected'));
        $builder->addTransition(new \Symfony\Component\Workflow\Transition('re-draft', 'rejected', 'draft'));

        return $builder->build();
    }

    protected function createAvatar($author_name, $author_link, $author_image = 'path/to/author_image.jpg')
    {
        $template = imagecreatefrompng('path/to/template.png');
        $author_image = imagecreatefrompng($author_image);
        $stamp = imagecreatefrompng('path/to/stamp.png');

        $author_width = imagesx($author_image);
        $author_height = imagesy($author_image);

        $crop_width = min($author_width, $author_height);
        $crop_height = min($author_width, $author_height);

        $cropped_author_image = imagecreatetruecolor($crop_width, $crop_height);
        imagecopyresized($cropped_author_image, $author_image, 0, 0, ($author_width - $crop_width) / 2, ($author_height - $crop_height) / 2, $crop_width, $crop_height, $crop_width, $crop_height);
        imagecopyresampled($template, $cropped_author_image, 53, 80, 0, 0, 240, 260, $crop_width, $crop_height);

        imagecopy($template, $stamp, 175, 125, 0, 0, 300, 300);

        $text_color = imagecolorallocate($template, 0, 0, 0);

        $font = 'path/to/font.ttf';

        $text_width = imagettfbbox(20, -1, $font, $author_name);
        $text_width = $text_width[2] - $text_width[0];

        $x = (imagesx($template) - $text_width) / 2;
        $y = 440;

        imagettftext($template, 20, -1, $x, $y, $text_color, $font, $author_name);

        $text_width = imagettfbbox(20, -1, $font, $author_link);
        $text_width = $text_width[2] - $text_width[0];

        $x = (imagesx($template) - $text_width) / 2;
        $y = 575;

        imagettftext($template, 20, -1, $x, $y, $text_color, $font, $author_link);

        $temp_file = tempnam(sys_get_temp_dir(), 'img');
        imagepng($template, $temp_file);

        return curl_file_create($temp_file);
    }


    public function translate(string $key)
    {
        return $this->container->get('bot_translator')->translate($key);
    }
}
